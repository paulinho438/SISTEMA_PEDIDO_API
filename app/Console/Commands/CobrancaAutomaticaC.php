<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Juros;
use App\Models\Parcela;
use App\Models\Feriado;
use App\Services\WAPIService;
use Illuminate\Support\Facades\File;
use Efi\Exception\EfiException;
use Efi\EfiPay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CobrancaAutomaticaC extends Command
{
    protected $signature = 'cobranca:AutomaticaC';
    protected $description = 'Cobrança automatica das parcelas em atraso';

    public function handle()
    {
        $this->info('Realizando a Cobrança Automatica das Parcelas em Atrasos');

        $today = Carbon::today()->toDateString();
        $isHoliday = Feriado::where('data_feriado', $today)->exists();

        if ($isHoliday) return 0;

        $todayHoje = now();

        $parcelasQuery = Parcela::whereNull('dt_baixa')->with('emprestimo');

        if ($todayHoje->isSaturday() || $todayHoje->isSunday()) {
            $parcelasQuery->where('atrasadas', '>', 0);
        }

        $parcelasQuery->orderByDesc('id');
        $parcelas = $parcelasQuery->get();

        if ($todayHoje->isSaturday() || $todayHoje->isSunday()) {
            $parcelas = $parcelas->filter(function ($parcela) {
                $dataProtesto = optional($parcela->emprestimo)->data_protesto;
                return !$dataProtesto || !Carbon::parse($dataProtesto)->lte(Carbon::now()->subDays(1));
            });
        } else {
            $parcelas = $parcelas->filter(function ($parcela) use ($todayHoje) {
                $emprestimo = $parcela->emprestimo;
                $deveCobrarHoje = $emprestimo && $emprestimo->deve_cobrar_hoje && Carbon::parse($emprestimo->deve_cobrar_hoje)->isSameDay($todayHoje);
                $vencimentoHoje = $parcela->venc_real && Carbon::parse($parcela->venc_real)->isSameDay($todayHoje);
                return $deveCobrarHoje || $vencimentoHoje;
            });
        }

        $parcelas = $parcelas->unique('emprestimo_id')->values();

        foreach ($parcelas as $parcela) {
            sleep(4);
            if ($this->emprestimoEmProtesto($parcela)) continue;

            if (!self::podeProcessarParcela($parcela)) {
                continue;
            }

            if (
                $parcela->emprestimo &&
                $parcela->emprestimo->contaspagar &&
                $parcela->emprestimo->contaspagar->status == "Pagamento Efetuado"
            ) {

                $telefone = preg_replace('/\D/', '', $parcela->emprestimo->client->telefone_celular_1);
                $telefoneCliente = "55" . $telefone;

                $saudacao = self::obterSaudacao();
                $saudacaoTexto = "$saudacao, " . $parcela->emprestimo->client->nome_completo . "!";
                $fraseInicial = "

🤷‍♂️ Última chamada, Ainda não identificamos seu pagamento na data de hoje, lembrando que é até 40 minutos para processar, será aplicado multas e entrará na rota de cobrança caso não tenha pago!

⚠️  *sempre enviar o comprovante para ajudar na conferência não se esqueça*

Segue abaixo link para pagamento parcela e acesso todo o histórico de parcelas:

https://sistema.agecontrole.com.br/#/parcela/{$parcela->id}

📲 Para mais informações WhatsApp {$parcela->emprestimo->company->numero_contato}
";

                $frase = $saudacaoTexto . $fraseInicial;

                $wapiService = new WAPIService();
                $wapiService->enviarMensagem(
                    $parcela->emprestimo->company->token_api_wtz,
                    $parcela->emprestimo->company->instance_id,
                    ["phone" => $telefoneCliente, "message" => $frase]
                );

                sleep(1);

                if ($parcela->emprestimo->company->mensagem_audio && $parcela->atrasadas > 0) {
                    $tipo = match ($parcela->atrasadas) {
                        2 => "1.3", 4 => "2.3", 6 => "3.3",
                        8 => "4.3", 10 => "5.3", 15 => "6.3",
                        default => "0"
                    };

                    $audioMap = [
                        "1.3" => "mensagem_3_atraso_2d.ogg",
                        "2.3" => "mensagem_3_atraso_4d.ogg",
                        "3.3" => "mensagem_3_atraso_6d.ogg",
                        "4.3" => "mensagem_3_atraso_8d.ogg",
                        "5.3" => "mensagem_3_atraso_10d.ogg",
                        "6.3" => "mensagem_3_atraso_15d.ogg"
                    ];

                    if (isset($audioMap[$tipo])) {
                        $caminhoArquivo = storage_path("app/public/audios/{$audioMap[$tipo]}");

                        if (File::exists($caminhoArquivo)) {
                            $conteudo = File::get($caminhoArquivo);
                            $base64 = 'data:audio/ogg;base64,' . base64_encode($conteudo);

                            $wapiService->enviarMensagemAudio(
                                $parcela->emprestimo->company->token_api_wtz,
                                $parcela->emprestimo->company->instance_id,
                                ["phone" => $telefoneCliente, "audio" => $base64]
                            );
                        }
                    }
                }

                if (count($parcela->emprestimo->parcelas) == 1 && $parcela->atrasadas == 0) {
                    $caminhoArquivo = storage_path("app/public/audios/msginfo3.ogg");
                    if (File::exists($caminhoArquivo)) {
                        $conteudo = File::get($caminhoArquivo);
                        $base64 = 'data:audio/ogg;base64,' . base64_encode($conteudo);

                        $wapiService->enviarMensagemAudio(
                            $parcela->emprestimo->company->token_api_wtz,
                            $parcela->emprestimo->company->instance_id,
                            ["phone" => $telefoneCliente, "audio" => $base64]
                        );
                    }
                }
            }
        }

        exit;
    }

    function obterSaudacao()
    {
        $hora = date('H');
        $saudacoesManha = ['🌤️ Bom dia', '👋 Olá, bom dia', '🌤️ Tenha um excelente dia'];
        $saudacoesTarde = ['🌤️ Boa tarde', '👋 Olá, boa tarde', '🌤️ Espero que sua tarde esteja ótima'];
        $saudacoesNoite = ['🌤️ Boa noite', '👋 Olá, boa noite', '🌤️ Espero que sua noite esteja ótima'];

        if ($hora < 12) return $saudacoesManha[array_rand($saudacoesManha)];
        if ($hora < 18) return $saudacoesTarde[array_rand($saudacoesTarde)];
        return $saudacoesNoite[array_rand($saudacoesNoite)];
    }

    function encontrarPrimeiraParcelaPendente($parcelas)
    {
        foreach ($parcelas as $parcela) {
            if ($parcela->dt_baixa === '' || $parcela->dt_baixa === null) {
                return $parcela;
            }
        }
        return null;
    }

    private function emprestimoEmProtesto($parcela)
    {
        if (!$parcela->emprestimo || !$parcela->emprestimo->data_protesto) return false;
        return Carbon::parse($parcela->emprestimo->data_protesto)->lte(Carbon::now()->subDays(14));
    }

    private static function podeProcessarParcela($parcela)
    {
        $parcelaPesquisa = Parcela::find($parcela->id);

        if ($parcelaPesquisa->venc_real->isSameDay(Carbon::today()) && $parcelaPesquisa->dt_baixa == null) {
            return true;
        }

        if ($parcelaPesquisa->dt_baixa !== null) {
            Log::info("Parcela {$parcela->id} já baixada, não será processada novamente.");
            return false;
        }

        if($parcelaPesquisa->atrasadas == 0){
            Log::info("Parcela {$parcela->id} não está mais atrasada, não será processada novamente.");
            return false;
        }

        return true;
    }
}

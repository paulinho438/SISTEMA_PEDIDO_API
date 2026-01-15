<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Parcela;
use App\Models\Feriado;
use App\Models\BotaoCobranca;
use App\Services\WAPIService;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CobrancaAutomaticaBBotao extends Command
{
    protected $signature = 'cobranca:AutomaticaBBotao';
    protected $description = 'Cobrança automatica das parcelas em atraso';

    public function handle()
    {
        $this->info('Realizando a Cobrança Automatica das Parcelas em Atrasos');

        $presseds = BotaoCobranca::where('is_active', true)->where('click_count', 2)->get();

        foreach ($presseds as $pressed) {
            $pressed->update(['is_active' => false]);

            $today = Carbon::today()->toDateString();
            $isHoliday = Feriado::where('data_feriado', $today)->exists();

            $parcelas = collect();

            if (!$isHoliday) {
                $parcelas = Parcela::whereNull('dt_baixa')
                    ->whereNull('valor_recebido_pix')
                    ->whereNull('valor_recebido')
                    ->whereDate('venc_real', $today)
                    ->whereHas('emprestimo', function ($query) use ($pressed) {
                        $query->where('company_id', $pressed->company_id);
                    })
                    ->orderByDesc('id')
                    ->get()
                    ->unique('emprestimo_id');
            }

            foreach ($parcelas as $parcela) {
                sleep(4);
                if (
                    $parcela->emprestimo->banco->wallet == 0 ||
                    !$parcela->emprestimo->contaspagar ||
                    $parcela->emprestimo->contaspagar->status !== "Pagamento Efetuado"
                ) {
                    continue;
                }

                try {
                        $telefone = preg_replace('/\D/', '', $parcela->emprestimo->client->telefone_celular_1);
                        $telefoneCliente = "55" . $telefone;
                        $company = $parcela->emprestimo->company;

                        $saudacao = self::obterSaudacao();
                        $parcelaPendente = self::encontrarPrimeiraParcelaPendente($parcela->emprestimo->parcelas);

                        $frase = "{$saudacao}, " . $parcela->emprestimo->client->nome_completo . "!\n\n"
                            . "🤷‍♂️ Não identificamos seu pagamento na data de hoje, evite multas!\n\n"
                            . "Segue abaixo link para pagamento parcela e acesso todo o histórico de parcelas:\n\n"
                            . "https://sistema.agecontrole.com.br/#/parcela/{$parcela->id}\n\n"
                            . "📲 Para mais informações WhatsApp {$company->numero_contato}";

                        $wapiService = new WAPIService();
                        $wapiService->enviarMensagem(
                            $company->token_api_wtz,
                            $company->instance_id,
                            [
                                "phone" => $telefoneCliente,
                                "message" => $frase
                            ]
                        );

                        sleep(1);

                        if ($company->mensagem_audio && $parcela->atrasadas > 0) {
                            $audioMap = [
                                2 => "mensagem_2_atraso_2d.ogg",
                                4 => "mensagem_2_atraso_4d.ogg",
                                6 => "mensagem_2_atraso_6d.ogg",
                                8 => "mensagem_2_atraso_8d.ogg",
                                10 => "mensagem_2_atraso_10d.ogg",
                                15 => "mensagem_2_atraso_15d.ogg"
                            ];

                            if (isset($audioMap[$parcela->atrasadas])) {
                                $nomeArquivo = $audioMap[$parcela->atrasadas];
                                $caminhoArquivo = storage_path("app/public/audios/{$nomeArquivo}");

                                if (File::exists($caminhoArquivo)) {
                                    $conteudo = File::get($caminhoArquivo);
                                    $base64 = 'data:audio/ogg;base64,' . base64_encode($conteudo);

                                    $wapiService->enviarMensagemAudio(
                                        $company->token_api_wtz,
                                        $company->instance_id,
                                        [
                                            "phone" => $telefoneCliente,
                                            "audio" => $base64
                                        ]
                                    );
                                }
                            }
                        }
                } catch (\Throwable $th) {
                    dd($th);
                }
            }
        }
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
            if (empty($parcela->dt_baixa)) {
                return $parcela;
            }
        }
        return null;
    }
}

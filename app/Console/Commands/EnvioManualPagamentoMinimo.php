<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Http;

use App\Models\Banco;
use App\Models\Parcela;
use App\Models\PagamentoMinimo;
use App\Models\User;
use App\Models\Movimentacaofinanceira;

use Efi\Exception\EfiException;
use Efi\EfiPay;

use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class EnvioManualPagamentoMinimo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baixa:AutomaticaPagamentoMinimo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realizando as Baixas Automaticas Quitacao';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Realizando as Baixas');



        $bancos = Banco::all();
        $pagamentoMinimo = new PagamentoMinimo;


        $parcela = new Parcela;


        foreach ($bancos as $banco) {
            // $parcelas = $parcela->where('dt_baixa', null)->whereDate('venc_real', '<=', Carbon::now()->toDateString())->whereHas('emprestimo', function ($query) use ($banco) {
            //     $query->whereHas('banco', function ($query) use ($banco) {
            //         $query->where('id', $banco->id);
            //     });
            // })->get();

            if ($banco['wallet'] == 1) {


                $quitacaoQry = $pagamentoMinimo->where('dt_baixa', null)->whereHas('emprestimo', function ($query) use ($banco) {
                    $query->whereHas('banco', function ($query) use ($banco) {
                        $query->where('id', $banco->id);
                    });
                })->get();

                $primeiroRegistro = $parcela->where('dt_baixa', null)->whereHas('emprestimo', function ($query) use ($banco) {
                    $query->whereHas('banco', function ($query) use ($banco) {
                        $query->where('id', $banco->id);
                    });
                })->orderBy('dt_lancamento')->first();

                $ultimoRegistro = $parcela->where('dt_baixa', null)->whereHas('emprestimo', function ($query) use ($banco) {
                    $query->whereHas('banco', function ($query) use ($banco) {
                        $query->where('id', $banco->id);
                    });
                })->orderBy('venc', 'desc')->first();

                $caminhoAbsoluto = storage_path('app/public/documentos/' . $banco['certificado']);
                $options = [
                    'clientId' => $banco['clienteid'],
                    'clientSecret' => $banco['clientesecret'],
                    'certificate' => $caminhoAbsoluto,
                    'sandbox' => false,
                    "debug" => false,
                    'timeout' => 60,
                ];



                $params = [
                    "inicio" => $primeiroRegistro->dt_lancamento . "T00:00:00Z",
                    "fim" => $ultimoRegistro->venc_real . "T23:59:59Z",
                    "status" => "CONCLUIDA", // "ATIVA","CONCLUIDA", "REMOVIDA_PELO_USUARIO_RECEBEDOR", "REMOVIDA_PELO_PSP"
                ];

                try {
                    $api = new EfiPay($options);
                    $response = $api->pixListDueCharges($params);

                    // Array para armazenar os valores de "id" de "loc"
                    $arrayIdsLoc = [];

                    // Loop através do array original
                    foreach ($response['cobs'] as $item) {
                        // Verifica se a chave "loc" existe e se a chave "id" está presente dentro de "loc"
                        if (isset($item['loc']['id'])) {
                            // Adiciona o valor de "id" ao novo array
                            $arrayIdsLoc[] = $item['loc']['id'];
                        }
                    }

                    foreach ($quitacaoQry as $item) {
                        if (in_array($item->identificador, $arrayIdsLoc)) {

                            $q = PagamentoMinimo::find($item->id);
                            $q->dt_baixa = date('Y-m-d');
                            $q->save();


                            $dt_lancamento = Carbon::parse($q->emprestimo->parcela[0]->dt_lancamento);
                            $venc = Carbon::parse($q->emprestimo->parcela[0]->venc);

                            $differenceInDays = $dt_lancamento->diffInDays($venc);

                            $q->emprestimo->parcela[0]->venc_real = $q->emprestimo->parcela[0]->venc_real->addDays($differenceInDays * $q->emprestimo->parcela[0]->atrasadas);

                            $q->emprestimo->parcela[0]->save();

                            // foreach ($q->emprestimo->parcelas as $parcela) {
                            //     if ($parcela->dt_baixa == null) {
                            //         $parcela->dt_baixa = date('Y-m-d');
                            //         $parcela->save();

                            //         $parcela->contasreceber->status = 'Pago';
                            //         $parcela->contasreceber->dt_baixa = date('Y-m-d');
                            //         $parcela->contasreceber->forma_recebto = 'PIX';
                            //         $parcela->contasreceber->save();

                            //         # MOVIMENTAÇÃO FINANCEIRA DE SAIDA REFERENTE A TAXA DE JUROS

                            //         $valor = $parcela->saldo;
                            //         $taxa = $parcela->emprestimo->banco->juros / 100;
                            //         $juros = $valor * $taxa;


                            //         # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL

                            //         $movimentacaoFinanceira = [];
                            //         $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                            //         $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                            //         $movimentacaoFinanceira['descricao'] = 'Baixa automática da parcela Nº ' . $parcela->parcela . ' do emprestimo n° ' . $parcela->emprestimo_id;
                            //         $movimentacaoFinanceira['tipomov'] = 'E';
                            //         $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                            //         $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                            //         $movimentacaoFinanceira['valor'] = $parcela->saldo - $juros;

                            //         Movimentacaofinanceira::create($movimentacaoFinanceira);

                            //         # ADICIONANDO O VALOR NO SALDO DO BANCO

                            //         $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $parcela->saldo - $juros;
                            //         $parcela->emprestimo->banco->save();


                            //         $movimentacaoFinanceira = [];
                            //         $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                            //         $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                            //         $movimentacaoFinanceira['descricao'] = 'Juros de ' . $parcela->emprestimo->banco->juros . '% referente a baixa automática via pix da parcela Nº ' . $parcela->parcela . ' do emprestimo n° ' . $parcela->emprestimo_id;
                            //         $movimentacaoFinanceira['tipomov'] = 'S';
                            //         $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                            //         $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                            //         $movimentacaoFinanceira['valor'] = $juros;

                            //         Movimentacaofinanceira::create($movimentacaoFinanceira);

                            //     }
                            // }

                        }
                    }


                    print_r("<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</pre>");
                } catch (EfiException $e) {
                    print_r($e->code . "<br>");
                    print_r($e->error . "<br>");
                    print_r($e->errorDescription) . "<br>";
                } catch (\Exception $e) {
                    print_r($e->getMessage());
                }

            }




        }

        exit;









    }
}

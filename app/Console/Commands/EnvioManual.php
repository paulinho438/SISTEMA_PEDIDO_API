<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Http;

use App\Models\Banco;
use App\Models\Parcela;
use App\Models\User;
use App\Models\Movimentacaofinanceira;
use App\Models\CustomLog;

use Efi\Exception\EfiException;
use Efi\EfiPay;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Str;

use Carbon\Carbon;

class EnvioManual extends Command
{


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'baixa:Automatica';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realizando as Baixas Automaticas';

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
        $parcela = new Parcela;


        foreach ($bancos as $banco) {
            // $parcelas = $parcela->where('dt_baixa', null)->whereDate('venc_real', '<=', Carbon::now()->toDateString())->whereHas('emprestimo', function ($query) use ($banco) {
            //     $query->whereHas('banco', function ($query) use ($banco) {
            //         $query->where('id', $banco->id);
            //     });
            // })->get();

            if ($banco['wallet'] == 1) {

                $parcelas = $parcela->where('dt_baixa', null)->whereHas('emprestimo', function ($query) use ($banco) {
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
                DB::beginTransaction();

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

                    print_r("<pre>" . json_encode($arrayIdsLoc) . "</pre>");

                    foreach ($parcelas as $item) {
                        if (in_array($item->identificador, $arrayIdsLoc)) {
                            $editParcela = Parcela::find($item->id);
                            $editParcela->dt_baixa = date('Y-m-d');
                            $editParcela->save();
                            if ($editParcela->contasreceber) {
                                $editParcela->contasreceber->status = 'Pago';
                                $editParcela->contasreceber->dt_baixa = date('Y-m-d');
                                $editParcela->contasreceber->forma_recebto = 'PIX';
                                $editParcela->contasreceber->save();

                                # MOVIMENTAÇÃO FINANCEIRA DE SAIDA REFERENTE A TAXA DE JUROS

                                $valorPago = $editParcela->saldo;

                                $valor = $editParcela->saldo;
                                $taxa = $editParcela->emprestimo->banco->juros / 100;
                                $juros = $valor * $taxa;


                                # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL

                                $movimentacaoFinanceira = [];
                                $movimentacaoFinanceira['banco_id'] = $editParcela->emprestimo->banco_id;
                                $movimentacaoFinanceira['company_id'] = $editParcela->emprestimo->company_id;
                                $movimentacaoFinanceira['descricao'] = 'Baixa automática da parcela Nº ' . $editParcela->parcela . ' do emprestimo n° ' . $editParcela->emprestimo_id;
                                $movimentacaoFinanceira['tipomov'] = 'E';
                                $movimentacaoFinanceira['parcela_id'] = $editParcela->id;
                                $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                $movimentacaoFinanceira['valor'] = $editParcela->saldo - $juros;

                                Movimentacaofinanceira::create($movimentacaoFinanceira);

                                # ADICIONANDO O VALOR NO SALDO DO BANCO

                                $editParcela->emprestimo->banco->saldo = $editParcela->emprestimo->banco->saldo + $editParcela->saldo - $juros;
                                $editParcela->emprestimo->banco->save();


                                $movimentacaoFinanceira = [];
                                $movimentacaoFinanceira['banco_id'] = $editParcela->emprestimo->banco_id;
                                $movimentacaoFinanceira['company_id'] = $editParcela->emprestimo->company_id;
                                $movimentacaoFinanceira['descricao'] = 'Juros de ' . $editParcela->emprestimo->banco->juros . '% referente a baixa automática via pix da parcela Nº ' . $editParcela->parcela . ' do emprestimo n° ' . $editParcela->emprestimo_id;
                                $movimentacaoFinanceira['tipomov'] = 'S';
                                $movimentacaoFinanceira['parcela_id'] = $editParcela->id;
                                $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                $movimentacaoFinanceira['valor'] = $juros;

                                Movimentacaofinanceira::create($movimentacaoFinanceira);

                                if ($editParcela->emprestimo->quitacao->chave_pix) {

                                    $editParcela->emprestimo->quitacao->valor = $editParcela->emprestimo->parcelas[0]->totalPendente();
                                    $editParcela->emprestimo->quitacao->saldo = $editParcela->emprestimo->parcelas[0]->totalPendente();
                                    $editParcela->emprestimo->quitacao->save();

                                    $gerarPixQuitacao = self::gerarPixQuitacao(
                                        [
                                            'banco' => [
                                                'client_id' => $editParcela->emprestimo->banco->clienteid,
                                                'client_secret' => $editParcela->emprestimo->banco->clientesecret,
                                                'certificado' => $editParcela->emprestimo->banco->certificado,
                                                'chave' => $editParcela->emprestimo->banco->chavepix,
                                            ],
                                            'parcela' => [
                                                'parcela' => $editParcela->parcela,
                                                'valor' => $editParcela->emprestimo->parcelas[0]->totalPendente(),
                                                'venc_real' => date('Y-m-d'),
                                            ],
                                            'cliente' => [
                                                'nome_completo' => $editParcela->emprestimo->client->nome_completo,
                                                'cpf' => $editParcela->emprestimo->client->cpf
                                            ]
                                        ]
                                    );

                                    $editParcela->emprestimo->quitacao->identificador = $gerarPixQuitacao['identificador'];
                                    $editParcela->emprestimo->quitacao->chave_pix = $gerarPixQuitacao['chave_pix'];

                                    $editParcela->emprestimo->quitacao->save();

                                }

                            }


                        }
                    }

                    DB::commit();

                    print_r("<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</pre>");
                } catch (EfiException $e) {
                    DB::rollBack();
                    print_r($e->code . "<br>");
                    print_r($e->error . "<br>");
                    print_r($e->errorDescription) . "<br>";
                } catch (\Exception $e) {
                    DB::rollBack();
                    print_r($e->getMessage());
                }

            }




        }

        exit;









    }

    public function gerarPixQuitacao($dados)
    {

        $return = [];

        $caminhoAbsoluto = storage_path('app/public/documentos/' . $dados['banco']['certificado']);
        $options = [
            'clientId' => $dados['banco']['client_id'],
            'clientSecret' => $dados['banco']['client_secret'],
            'certificate' => $caminhoAbsoluto,
            'sandbox' => false,
            "debug" => false,
            'timeout' => 60,
        ];

        $params = [
            "txid" => Str::random(32)
        ];

        $body = [
            "calendario" => [
                "dataDeVencimento" => $dados['parcela']['venc_real'],
                "validadeAposVencimento" => 0
            ],
            "devedor" => [
                "nome" => $dados['cliente']['nome_completo'],
                "cpf" => str_replace(['-', '.'], '', $dados['cliente']['cpf']),
            ],
            "valor" => [
                "original" => number_format(str_replace(',', '', $dados['parcela']['valor']), 2, '.', ''),

            ],
            "chave" => $dados['banco']['chave'], // Pix key registered in the authenticated Efí account
            "solicitacaoPagador" => "Parcela " . $dados['parcela']['parcela'],
            "infoAdicionais" => [
                [
                    "nome" => "Emprestimo",
                    "valor" => "R$ " . $dados['parcela']['valor'],
                ]
            ]
        ];

        try {
            $api = new EfiPay($options);
            $pix = $api->pixCreateDueCharge($params, $body);


            if ($pix["txid"]) {
                $params = [
                    "id" => $pix["loc"]["id"]
                ];

                $return['identificador'] = $pix["loc"]["id"];


                try {
                    $qrcode = $api->pixGenerateQRCode($params);

                    $return['chave_pix'] = $qrcode['linkVisualizacao'];

                    return $return;

                } catch (EfiException $e) {
                    print_r($e->code . "<br>");
                    print_r($e->error . "<br>");
                    print_r($e->errorDescription) . "<br>";
                } catch (\Exception $e) {
                    print_r($e->getMessage());
                }
            } else {
                echo "<pre>" . json_encode($pix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</pre>";
            }
        } catch (EfiException $e) {
            print_r($e->code . "<br>");
            print_r($e->error . "<br>");
            print_r($e->errorDescription) . "<br>";
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
    }
}

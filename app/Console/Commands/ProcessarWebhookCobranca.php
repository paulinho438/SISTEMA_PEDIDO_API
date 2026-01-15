<?php

namespace App\Console\Commands;

use App\Jobs\EnviarMensagemWhatsApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\WebhookCobranca;
use App\Models\Parcela;
use App\Models\ControleBcodex;
use App\Models\Movimentacaofinanceira;
use App\Models\Locacao;
use App\Models\PagamentoMinimo;
use App\Models\Quitacao;
use App\Models\PagamentoPersonalizado;
use App\Models\PagamentoSaldoPendente;
use App\Models\Deposito;


use App\Mail\ExampleEmail;
use Illuminate\Support\Facades\Mail;

use App\Services\BcodexService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessarWebhookCobranca extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:baixaBcodex';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cobrança automatica das parcelas em atraso';

    protected $bcodexService;

    public function __construct(BcodexService $bcodexService)
    {
        $this->bcodexService = $bcodexService;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): void
    {
        $this->info('Realizando a Cobrança Automatica das Parcelas em Atrasos');

        Log::info("Cobranca Automatica A inicio de rotina");

        WebhookCobranca::where('processado', false)->chunk(50, function ($lotes) {
            foreach ($lotes as $registro) {
                $data = $registro->payload;

                // Processamento atual (todo o conteúdo da função original)
                // Você pode extrair cada tipo (parcela, locação, mínimo, etc.) para métodos separados

                //REFERENTE A PARCELAS
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $parcela = Parcela::where('identificador', $txId)->whereNull('dt_baixa')->first();

                        if ($parcela) {
                            $parcela->saldo = 0;
                            $parcela->dt_baixa = $horario;
                            $parcela->save();

                            if ($parcela->contasreceber) {
                                $parcela->contasreceber->status = 'Pago';
                                $parcela->contasreceber->dt_baixa = date('Y-m-d');
                                $parcela->contasreceber->forma_recebto = 'PIX';
                                $parcela->contasreceber->save();

                                # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL

                                $movimentacaoFinanceira = [];
                                $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                                $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                                $movimentacaoFinanceira['descricao'] = sprintf(
                                    'Baixa automática da parcela Nº %d do empréstimo Nº %d do cliente %s, pagador: %s',
                                    $parcela->id,
                                    $parcela->emprestimo_id,
                                    $parcela->emprestimo->client->nome_completo,
                                    $pix['pagador']['nome']
                                );
                                $movimentacaoFinanceira['tipomov'] = 'E';
                                $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                                $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                $movimentacaoFinanceira['valor'] = $valor;

                                Movimentacaofinanceira::create($movimentacaoFinanceira);

                                # ADICIONANDO O VALOR NO SALDO DO BANCO

                                $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $valor;
                                $parcela->emprestimo->banco->save();

                                // $movimentacaoFinanceira = [];
                                // $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                                // $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                                // $movimentacaoFinanceira['descricao'] = 'Juros de ' . $parcela->emprestimo->banco->juros . '% referente a baixa automática via pix da parcela Nº ' . $parcela->parcela . ' do emprestimo n° ' . $parcela->emprestimo_id;
                                // $movimentacaoFinanceira['tipomov'] = 'S';
                                // $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                                // $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                // $movimentacaoFinanceira['valor'] = $juros;

                                // Movimentacaofinanceira::create($movimentacaoFinanceira);

                                if ($parcela->emprestimo->quitacao->chave_pix) {

                                    $parcela->emprestimo->quitacao->valor = $parcela->emprestimo->parcelas[0]->totalPendente();
                                    $parcela->emprestimo->quitacao->saldo = $parcela->emprestimo->parcelas[0]->totalPendente();
                                    $parcela->emprestimo->quitacao->save();

                                    $response = $this->bcodexService->criarCobranca($parcela->emprestimo->parcelas[0]->totalPendente(), $parcela->emprestimo->banco->document, null);

                                    if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                        $parcela->emprestimo->quitacao->identificador = $response->json()['txid'];
                                        $parcela->emprestimo->quitacao->chave_pix = $response->json()['pixCopiaECola'];
                                        $parcela->emprestimo->quitacao->save();
                                    }
                                }
                            }

                            $proximaParcela = $parcela->emprestimo->parcelas->firstWhere('dt_baixa', null);

                            if ($proximaParcela) {
                                if ($proximaParcela->emprestimo->pagamentosaldopendente && $proximaParcela->emprestimo->pagamentosaldopendente->chave_pix) {

                                    $proximaParcela->emprestimo->pagamentosaldopendente->valor = $proximaParcela->saldo;

                                    $proximaParcela->emprestimo->pagamentosaldopendente->save();

                                    $response = $this->bcodexService->criarCobranca($proximaParcela->emprestimo->pagamentosaldopendente->valor, $proximaParcela->emprestimo->banco->document, null);

                                    if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                        $proximaParcela->emprestimo->pagamentosaldopendente->identificador = $response->json()['txid'];
                                        $proximaParcela->emprestimo->pagamentosaldopendente->chave_pix = $response->json()['pixCopiaECola'];
                                        $proximaParcela->emprestimo->pagamentosaldopendente->save();
                                    }
                                }
                            }
                        }
                    }
                }

                //REFERENTE A LOCACAO
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $locacao = Locacao::where('identificador', $txId)->whereNull('data_pagamento')->first();
                        if ($locacao) {
                            $locacao->data_pagamento = $horario;
                            $locacao->save();

                            $details = [
                                'title' => 'Relatório de Emprestimos',
                                'body' => 'This is a test email using MailerSend in Laravel.'
                            ];

                            Mail::to($locacao->company->email)->send(new ExampleEmail($details, $locacao));
                        }
                    }
                }

                //REFERENTE A PAGAMENTO MINIMO
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $minimo = PagamentoMinimo::where('identificador', $txId)->whereNull('dt_baixa')->first();
                        if ($minimo) {

                            $juros = 0;

                            $parcela = Parcela::where('emprestimo_id', $minimo->emprestimo_id)->first();

                            if ($parcela) {

                                $parcela->saldo -= $minimo->valor;

                                //valor usado lá na frente em pagamento minimo
                                $juros = $parcela->emprestimo->juros * $parcela->saldo / 100;

                                $parcela->saldo += $parcela->emprestimo->juros * $parcela->saldo / 100;

                                $dataInicialCarbon = Carbon::parse($parcela->dt_lancamento);
                                $dataFinalCarbon = Carbon::parse($parcela->venc_real);

                                $dataInicial = Carbon::parse($parcela->venc_real);

                                $parcela->venc_real = $dataInicial->copy()->addMonth();

                                $response = $this->bcodexService->criarCobranca($minimo->valor, $parcela->emprestimo->banco->document, null);

                                if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                    $minimo->identificador = $response->json()['txid'];
                                    $minimo->chave_pix = $response->json()['pixCopiaECola'];
                                    $minimo->save();
                                }

                                $parcela->atrasadas = 0;
                                $parcela->save();


                                # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL

                                $movimentacaoFinanceira = [];
                                $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                                $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                                $movimentacaoFinanceira['descricao'] = sprintf(
                                    'Pagamento Minimo da parcela Nº %d do empréstimo Nº %d do cliente %s, pagador: %s',
                                    $parcela->id,
                                    $parcela->emprestimo_id,
                                    $parcela->emprestimo->client->nome_completo,
                                    $pix['pagador']['nome']
                                );
                                $movimentacaoFinanceira['tipomov'] = 'E';
                                $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                                $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                $movimentacaoFinanceira['valor'] = $minimo->valor;

                                Movimentacaofinanceira::create($movimentacaoFinanceira);

                                # ADICIONANDO O VALOR NO SALDO DO BANCO

                                $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $minimo->valor;
                                $parcela->emprestimo->banco->save();

                                if ($parcela->emprestimo->quitacao) {

                                    $parcela->emprestimo->quitacao->saldo = $parcela->totalPendente();
                                    $parcela->emprestimo->quitacao->save();
                                    $response = $this->bcodexService->criarCobranca($parcela->totalPendente(), $parcela->emprestimo->banco->document, null);

                                    if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                        $parcela->emprestimo->quitacao->identificador = $response->json()['txid'];
                                        $parcela->emprestimo->quitacao->chave_pix = $response->json()['pixCopiaECola'];
                                        $parcela->emprestimo->quitacao->saldo = $parcela->totalPendente();
                                        $parcela->emprestimo->quitacao->save();
                                    }
                                }

                                if ($parcela->emprestimo->pagamentominimo) {

                                    $parcela->emprestimo->pagamentominimo->valor = $juros;

                                    $parcela->emprestimo->pagamentominimo->save();

                                    $response = $this->bcodexService->criarCobranca($juros, $parcela->emprestimo->banco->document, null);

                                    if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                        $parcela->emprestimo->pagamentominimo->identificador = $response->json()['txid'];
                                        $parcela->emprestimo->pagamentominimo->chave_pix = $response->json()['pixCopiaECola'];
                                        $parcela->emprestimo->pagamentominimo->save();
                                    }
                                }


                                if ($parcela->emprestimo->pagamentosaldopendente && $parcela->emprestimo->pagamentosaldopendente->chave_pix) {

                                    $parcela->emprestimo->pagamentosaldopendente->valor = $parcela->saldo;

                                    $parcela->emprestimo->pagamentosaldopendente->save();

                                    $response = $this->bcodexService->criarCobranca($parcela->emprestimo->pagamentosaldopendente->valor, $parcela->emprestimo->banco->document, null);

                                    if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                        $parcela->emprestimo->pagamentosaldopendente->identificador = $response->json()['txid'];
                                        $parcela->emprestimo->pagamentosaldopendente->chave_pix = $response->json()['pixCopiaECola'];
                                        $parcela->emprestimo->pagamentosaldopendente->save();
                                    }
                                }
                            }
                        }
                    }
                }

                //REFERENTE A QUITACAO
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $quitacao = Quitacao::where('identificador', $txId)->whereNull('dt_baixa')->first();

                        if ($quitacao) {
                            $parcelas = Parcela::where('emprestimo_id', $quitacao->emprestimo_id)->get();

                            foreach ($parcelas as $parcela) {
                                $valorParcela = $parcela->saldo;
                                $parcela->saldo = 0;
                                $parcela->dt_baixa = Carbon::parse($pix['horario'])->toDateTimeString();
                                $parcela->save();

                                if ($parcela->contasreceber) {

                                    # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL

                                    $movimentacaoFinanceira = [];
                                    $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                                    $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                                    $movimentacaoFinanceira['descricao'] = sprintf(
                                        'Quitação da parcela Nº %d do empréstimo Nº %d do cliente %s, pagador: %s',
                                        $parcela->id,
                                        $parcela->emprestimo_id,
                                        $parcela->emprestimo->client->nome_completo,
                                        $pix['pagador']['nome']
                                    );
                                    $movimentacaoFinanceira['tipomov'] = 'E';
                                    $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                                    $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                    $movimentacaoFinanceira['valor'] = $valorParcela;

                                    Movimentacaofinanceira::create($movimentacaoFinanceira);

                                    # ADICIONANDO O VALOR NO SALDO DO BANCO

                                    $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $valorParcela;
                                    $parcela->emprestimo->banco->save();
                                }
                            }
                        }
                    }
                }

                //REFERENTE A PAGAMENTO PERSONALIZADO
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $pagamento = PagamentoPersonalizado::where('identificador', $txId)->whereNull('dt_baixa')->first();

                        if ($pagamento) {

                            $valor1 = $pagamento->emprestimo->pagamentominimo->valor;
                            $valor2 = $pagamento->emprestimo->pagamentosaldopendente->valor - $pagamento->emprestimo->pagamentominimo->valor;

                            $porcentagem = ($valor1 / $valor2);


                            $pagamento->dt_baixa = $horario;
                            $pagamento->save();

                            $parcela = Parcela::where('emprestimo_id', $pagamento->emprestimo_id)->whereNull('dt_baixa')->first();

                            $movimentacaoFinanceira = [];
                            $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                            $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                            $movimentacaoFinanceira['descricao'] = sprintf(
                                'Pagamento personalizado Nº %d do empréstimo Nº %d do cliente %s, pagador: %s',
                                $pagamento->id,
                                $parcela->emprestimo_id,
                                $parcela->emprestimo->client->nome_completo,
                                $pix['pagador']['nome']
                            );
                            $movimentacaoFinanceira['tipomov'] = 'E';
                            $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                            $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                            $movimentacaoFinanceira['valor'] = $valor;

                            Movimentacaofinanceira::create($movimentacaoFinanceira);

                            # ADICIONANDO O VALOR NO SALDO DO BANCO

                            $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $valor;
                            $parcela->emprestimo->banco->save();

                            $parcela->saldo -= $valor;
                            $parcela->save();

                            if ($parcela->saldo != 0) {


                                $novoAntigo = $parcela->saldo;
                                $novoValor = $novoAntigo + ($novoAntigo * $porcentagem);

                                $parcela->saldo = $novoValor;

                                $parcela->atrasadas = 0;

                                $dataInicial = Carbon::parse($parcela->venc_real);

                                $parcela->venc_real = $dataInicial->copy()->addMonth();

                                $parcela->save();

                                $response = $this->bcodexService->criarCobranca($parcela->saldo, $pagamento->emprestimo->banco->document, null);

                                if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                    $parcela->identificador = $response->json()['txid'];
                                    $parcela->chave_pix = $response->json()['pixCopiaECola'];
                                    $parcela->save();
                                }

                                $pagamento->emprestimo->pagamentosaldopendente->valor = $parcela->saldo;

                                $pagamento->emprestimo->pagamentosaldopendente->save();


                                $response = $this->bcodexService->criarCobranca($pagamento->emprestimo->pagamentosaldopendente->valor, $pagamento->emprestimo->banco->document, null);

                                if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                    $pagamento->emprestimo->pagamentosaldopendente->identificador = $response->json()['txid'];
                                    $pagamento->emprestimo->pagamentosaldopendente->chave_pix = $response->json()['pixCopiaECola'];
                                    $pagamento->emprestimo->pagamentosaldopendente->save();
                                }

                                $pagamento->emprestimo->pagamentominimo->valor = $novoValor - $novoAntigo;

                                $pagamento->emprestimo->pagamentominimo->save();

                                $response = $this->bcodexService->criarCobranca($pagamento->emprestimo->pagamentominimo->valor, $pagamento->emprestimo->banco->document, null);

                                if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                    $pagamento->emprestimo->pagamentominimo->identificador = $response->json()['txid'];
                                    $pagamento->emprestimo->pagamentominimo->chave_pix = $response->json()['pixCopiaECola'];
                                    $pagamento->emprestimo->pagamentominimo->save();
                                }
                            }
                        }
                    }
                }

                //REFERENTE A PAGAMENTO SALDO PENDENTE
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $valorInsert = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $pagamento = PagamentoSaldoPendente::where('identificador', $txId)->first();

                        if ($pagamento) {

                            $parcela = Parcela::where('emprestimo_id', $pagamento->emprestimo_id)
                                ->whereNull('dt_baixa')
                                ->orderBy('parcela', 'asc') // Ordena pela coluna 'parcela' em ordem ascendente
                                ->first();

                            while ($parcela && $valor > 0) {
                                if ($valor >= $parcela->saldo) {

                                    $movimentacaoFinanceira = [];
                                    $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                                    $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                                    $movimentacaoFinanceira['descricao'] = sprintf(
                                        'Baixa automática da parcela Nº %d do empréstimo Nº %d do cliente %s, pagador: %s',
                                        $parcela->id,
                                        $parcela->emprestimo_id,
                                        $parcela->emprestimo->client->nome_completo,
                                        $pix['pagador']['nome']
                                    );
                                    $movimentacaoFinanceira['tipomov'] = 'E';
                                    $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                                    $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                    $movimentacaoFinanceira['valor'] = $parcela->saldo;

                                    Movimentacaofinanceira::create($movimentacaoFinanceira);

                                    # ADICIONANDO O VALOR NO SALDO DO BANCO

                                    $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $parcela->saldo;
                                    $parcela->emprestimo->banco->save();


                                    // Quitar a parcela atual
                                    $valor -= $parcela->saldo;
                                    $parcela->saldo = 0;
                                    $parcela->dt_baixa = $horario;
                                } else {

                                    $movimentacaoFinanceira = [];
                                    $movimentacaoFinanceira['banco_id'] = $parcela->emprestimo->banco_id;
                                    $movimentacaoFinanceira['company_id'] = $parcela->emprestimo->company_id;
                                    $movimentacaoFinanceira['descricao'] = sprintf(
                                        'Baixa parcial automática da parcela Nº %d do empréstimo Nº %d do cliente %s, pagador: %s',
                                        $parcela->id,
                                        $parcela->emprestimo_id,
                                        $parcela->emprestimo->client->nome_completo,
                                        $pix['pagador']['nome']
                                    );
                                    $movimentacaoFinanceira['tipomov'] = 'E';
                                    $movimentacaoFinanceira['parcela_id'] = $parcela->id;
                                    $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                    $movimentacaoFinanceira['valor'] = $valor;

                                    Movimentacaofinanceira::create($movimentacaoFinanceira);

                                    # ADICIONANDO O VALOR NO SALDO DO BANCO

                                    $parcela->emprestimo->banco->saldo = $parcela->emprestimo->banco->saldo + $valor;
                                    $parcela->emprestimo->banco->save();

                                    // Reduzir o saldo da parcela atual
                                    $parcela->saldo -= $valor;
                                    $valor = 0;
                                }
                                $parcela->save();

                                // Encontrar a próxima parcela
                                $parcela = Parcela::where('emprestimo_id', $parcela->emprestimo_id)
                                    ->where('id', '>', $parcela->id)
                                    ->orderBy('id', 'asc')
                                    ->first();
                            }

                            $proximaParcela = null;

                            if ($parcela) {
                                $proximaParcela = $parcela->emprestimo->parcelas->firstWhere('dt_baixa', null);
                            }

                            if ($proximaParcela) {
                                $pagamento->valor = $proximaParcela->saldo;
                                $pagamento->save();

                                $response = $this->bcodexService->criarCobranca($proximaParcela->saldo, $parcela->emprestimo->banco->document, null);

                                if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                    $pagamento->identificador = $response->json()['txid'];
                                    $pagamento->chave_pix = $response->json()['pixCopiaECola'];
                                    $pagamento->save();
                                }
                            }

                            if ($proximaParcela) {
                                if ($proximaParcela->contasreceber) {
                                    $proximaParcela->contasreceber->status = 'Pago';
                                    $proximaParcela->contasreceber->dt_baixa = date('Y-m-d');
                                    $proximaParcela->contasreceber->forma_recebto = 'PIX';
                                    $proximaParcela->contasreceber->save();

                                    # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL


                                    // $movimentacaoFinanceira = [];
                                    // $movimentacaoFinanceira['banco_id'] = $proximaParcela->emprestimo->banco_id;
                                    // $movimentacaoFinanceira['company_id'] = $proximaParcela->emprestimo->company_id;
                                    // $movimentacaoFinanceira['descricao'] = 'Juros de ' . $proximaParcela->emprestimo->banco->juros . '% referente a baixa automática via pix da proximaParcela Nº ' . $proximaParcela->proximaParcela . ' do emprestimo n° ' . $proximaParcela->emprestimo_id;
                                    // $movimentacaoFinanceira['tipomov'] = 'S';
                                    // $movimentacaoFinanceira['proximaParcela_id'] = $proximaParcela->id;
                                    // $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                                    // $movimentacaoFinanceira['valor'] = $juros;

                                    // Movimentacaofinanceira::create($movimentacaoFinanceira);

                                    if ($parcela->emprestimo->quitacao->chave_pix) {

                                        $parcela->emprestimo->quitacao->valor = $parcela->emprestimo->parcelas[0]->totalPendente();
                                        $parcela->emprestimo->quitacao->saldo = $parcela->emprestimo->parcelas[0]->totalPendente();
                                        $parcela->emprestimo->quitacao->save();

                                        $txId = $parcela->emprestimo->quitacao->identificador ? $parcela->emprestimo->quitacao->identificador : null;
                                        $response = $this->bcodexService->criarCobranca($parcela->emprestimo->parcelas[0]->totalPendente(), $parcela->emprestimo->banco->document, null);

                                        if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                            $parcela->emprestimo->quitacao->identificador = $response->json()['txid'];
                                            $parcela->emprestimo->quitacao->chave_pix = $response->json()['pixCopiaECola'];
                                            $parcela->emprestimo->quitacao->save();
                                        }
                                    }

                                    if ($proximaParcela->emprestimo->pagamentosaldopendente && $proximaParcela->emprestimo->pagamentosaldopendente->chave_pix) {

                                        $proximaParcela->emprestimo->pagamentosaldopendente->valor = $proximaParcela->saldo;

                                        $proximaParcela->emprestimo->pagamentosaldopendente->save();
                                        $txId = $proximaParcela->emprestimo->pagamentosaldopendente->identificador ? $proximaParcela->emprestimo->pagamentosaldopendente->identificador : null;
                                        $response = $this->bcodexService->criarCobranca($proximaParcela->emprestimo->pagamentosaldopendente->valor, $proximaParcela->emprestimo->banco->document, null);

                                        if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {
                                            $proximaParcela->emprestimo->pagamentosaldopendente->identificador = $response->json()['txid'];
                                            $proximaParcela->emprestimo->pagamentosaldopendente->chave_pix = $response->json()['pixCopiaECola'];
                                            $proximaParcela->emprestimo->pagamentosaldopendente->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                //REFERENTE A DEPOSITO
                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $valor = $pix['valor'];
                        $horario = Carbon::parse($pix['horario'])->toDateTimeString();

                        // Encontrar a parcela correspondente
                        $deposito = Deposito::where('identificador', $txId)->whereNull('data_pagamento')->first();

                        if ($deposito) {

                            $deposito->banco->saldo += $valor;
                            $deposito->banco->save();

                            $deposito->data_pagamento = $horario;
                            $deposito->save();

                            # MOVIMENTAÇÃO FINANCEIRA DE ENTRADA REFERENTE A BAIXA MANUAL

                            $movimentacaoFinanceira = [];
                            $movimentacaoFinanceira['banco_id'] = $deposito->banco_id;
                            $movimentacaoFinanceira['company_id'] = $deposito->company_id;
                            $movimentacaoFinanceira['descricao'] = sprintf(
                                'Deposito Pagador: %s',
                                $pix['pagador']['nome'] ?? 'Não informado'

                            );
                            $movimentacaoFinanceira['tipomov'] = 'E';
                            $movimentacaoFinanceira['dt_movimentacao'] = date('Y-m-d');
                            $movimentacaoFinanceira['valor'] = $valor;

                            Movimentacaofinanceira::create($movimentacaoFinanceira);
                        }
                    }
                }

                //Controle de cobranca bcodex

                if (isset($data['pix']) && is_array($data['pix'])) {
                    foreach ($data['pix'] as $pix) {
                        $txId = $pix['txId'];
                        $controle = ControleBcodex::where('identificador', $txId)->first();

                        if ($controle) {
                            $controle->data_pagamento = Carbon::parse($pix['horario'])->toDateTimeString();
                            $controle->save();
                        }
                    }
                }

                // Após sucesso:
                $registro->processado = true;
                $registro->save();
            }
        });
    }

    private function processarParcela($parcela)
    {
        if (!$this->deveProcessarParcela($parcela)) {
            return;
        }

        if ($this->emprestimoEmProtesto($parcela)) {
            return;
        }

        try {
            $response = Http::get($parcela->emprestimo->company->whatsapp . '/logar');

            if ($response->successful() && $response->json()['loggedIn']) {
                $this->enviarMensagem($parcela);
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    private function deveProcessarParcela($parcela)
    {
        return isset($parcela->emprestimo->company->whatsapp) &&
            $parcela->emprestimo->contaspagar &&
            $parcela->emprestimo->contaspagar->status == "Pagamento Efetuado";
    }

    private function emprestimoEmProtesto($parcela)
    {
        if (!$parcela->emprestimo || !$parcela->emprestimo->data_protesto) {
            return false;
        }

        return Carbon::parse($parcela->emprestimo->data_protesto)->lte(Carbon::now()->subDays(14));
    }

    private function enviarMensagem($parcela)
    {
        $telefone = preg_replace('/\D/', '', $parcela->emprestimo->client->telefone_celular_1);
        $baseUrl = $parcela->emprestimo->company->whatsapp;


        $saudacao = $this->obterSaudacao();
        $mensagem = $this->montarMensagem($parcela, $saudacao);

        $data = [
            "numero" => "55" . $telefone,
            "mensagem" => $mensagem
        ];

        Http::asJson()->post("$baseUrl/enviar-mensagem", $data);
        Log::info("MENSAGEM ENVIADA: " . $telefone);
        sleep(4);
        if ($parcela->emprestimo->company->mensagem_audio) {
            if ($parcela->atrasadas > 0) {
                $baseUrl = $parcela->emprestimo->company->whatsapp;
                $tipo = "0";
                switch ($parcela->atrasadas) {
                    case 2:
                        $tipo = "1.1";
                        break;
                    case 4:
                        $tipo = "2.1";
                        break;
                    case 6:
                        $tipo = "3.1";
                        break;
                    case 8:
                        $tipo = "4.1";
                        break;
                    case 10:
                        $tipo = "5.1";
                        break;
                    case 15:
                        $tipo = "6.1";
                        break;
                }

                if ($tipo != "0") {
                    $data2 = [
                        "numero" => "55" . $telefone,
                        "nomeCliente" => $parcela->emprestimo->client->nome_completo,
                        "tipo" => $tipo
                    ];

                    Http::asJson()->post("$baseUrl/enviar-audio", $data2);
                }
            }
        }

        //identificar se o emprestimo é mensal
        //identificar se é a primeira cobranca
        if (count($parcela->emprestimo->parcelas) == 1) {
            if ($parcela->atrasadas == 0) {
                $data3 = [
                    "numero" => "55" . $telefone,
                    "nomeCliente" => "Sistema",
                    "tipo" => "msginfo1"
                ];

                Http::asJson()->post("$baseUrl/enviar-audio", $data3);
            }
        }
    }

    private function montarMensagem($parcela, $saudacao)
    {
        $saudacaoTexto = "{$saudacao}, " . $parcela->emprestimo->client->nome_completo . "!";
        $fraseInicial = "

Relatório de Parcelas Pendentes:

⚠️ *sempre enviar o comprovante para ajudar na conferência não se esqueça*

Segue abaixo link para pagamento parcela e acesso todo o histórico de parcelas:

https://sistema.agecontrole.com.br/#/parcela/{$parcela->id}

📲 Para mais informações WhatsApp {$parcela->emprestimo->company->numero_contato}
";
        return $saudacaoTexto . $fraseInicial;
    }

    private function obterSaudacao()
    {
        $hora = date('H');
        $saudacoesManha = ['🌤️ Bom dia', '👋 Olá, bom dia', '🌤️ Tenha um excelente dia'];
        $saudacoesTarde = ['🌤️ Boa tarde', '👋 Olá, boa tarde', '🌤️ Espero que sua tarde esteja ótima'];
        $saudacoesNoite = ['🌤️ Boa noite', '👋 Olá, boa noite', '🌤️ Espero que sua noite esteja ótima'];

        if ($hora < 12) {
            return $saudacoesManha[array_rand($saudacoesManha)];
        } elseif ($hora < 18) {
            return $saudacoesTarde[array_rand($saudacoesTarde)];
        } else {
            return $saudacoesNoite[array_rand($saudacoesNoite)];
        }
    }

    private function encontrarPrimeiraParcelaPendente($parcelas)
    {
        foreach ($parcelas as $parcela) {
            if (is_null($parcela->dt_baixa)) {
                return $parcela;
            }
        }

        return null;
    }
}

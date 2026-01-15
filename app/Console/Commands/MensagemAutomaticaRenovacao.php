<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use App\Models\Client;
use App\Services\WAPIService;
use Carbon\Carbon;

class MensagemAutomaticaRenovacao extends Command
{
    protected $signature = 'mensagem:AutomaticaRenovacao';
    protected $description = 'Mensagem Automática para Renovação de Empréstimos';

    public function handle()
    {
        $this->info('Iniciando envio automático de mensagem de renovação');

        $clients = Client::whereDoesntHave('emprestimos', function ($query) {
            $query->whereHas('parcelas', function ($q) {
                $q->whereNull('dt_baixa');
            });
        })
            ->with(['emprestimos' => function ($query) {
                $query->whereDoesntHave('parcelas', function ($q) {
                    $q->whereNull('dt_baixa');
                });
            }, 'company'])
            ->get();

        foreach ($clients as $client) {
            if (!$client->company || $client->company->envio_automatico_renovacao != 1) {
                continue;
            }

            $emprestimo = $client->emprestimos;

            if (!is_object($emprestimo)) {
                continue;
            }

            if (isset($emprestimo->mensagem_renovacao) && $emprestimo->mensagem_renovacao == 1) {
                continue;
            }

            if (!isset($emprestimo->count_late_parcels)) {
                continue;
            }

            $valorBase = $emprestimo->valor ?? 0;
            $valorOferta = $valorBase;
            $nome = $client->nome_completo;
            $mensagem = null;

            switch (true) {
                case ($emprestimo->count_late_parcels <= 2):
                    $mensagem = "Olá {$nome}, estamos entrando em contato para informar sobre seu empréstimo. Temos uma ótima notícia: você possui um valor pré-aprovado de R$ " . ($valorOferta + 100) . ". Gostaria de contratar?";
                    break;

                case ($emprestimo->count_late_parcels >= 3 && $emprestimo->count_late_parcels <= 5):
                    $mensagem = "Olá {$nome}, temos um valor pré-aprovado de R$ {$valorOferta} disponível para você. Gostaria de contratar?";
                    break;

                case ($emprestimo->count_late_parcels >= 6 && $emprestimo->count_late_parcels <= 10):
                    $mensagem = "Olá {$nome}, mesmo com pequenos atrasos, você ainda pode renovar com valor de R$ " . max(0, $valorOferta - 100) . ". Interessado?";
                    break;

                default:
                    $mensagem = null;
                    break;
            }

            if ($mensagem) {
                $this->enviarMensagem($client, $emprestimo, $mensagem);
                $emprestimo->mensagem_renovacao = 1;
                $emprestimo->save();
            }
        }

        $this->info('Mensagens de renovação enviadas com sucesso.');
    }

    public function enviarMensagem($cliente, $emprestimo, $frase)
    {
        try {
            $telefone = preg_replace('/\D/', '', $cliente->telefone_celular_1);
            $telefoneCliente = "55" . $telefone;
            $company = $emprestimo->company;

            if (!$company || !$company->token_api_wtz || !$company->instance_id) {
                return;
            }

            $wapiService = new WAPIService();
            $wapiService->enviarMensagem($company->token_api_wtz, $company->instance_id, [
                "phone" => $telefoneCliente,
                "message" => $frase
            ]);
            sleep(1);

        } catch (\Throwable $th) {
            report($th);
        }
    }
}

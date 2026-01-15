<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomLog;
use App\Models\Emprestimo;
use App\Services\PurchaseQuote\PurchaseQuoteDashboardService;
use Illuminate\Http\Request;
class DashboardController extends Controller
{
    protected CustomLog $custom_log;

    protected PurchaseQuoteDashboardService $purchaseQuoteDashboardService;

    public function __construct(Customlog $custom_log, PurchaseQuoteDashboardService $purchaseQuoteDashboardService)
    {
        $this->custom_log = $custom_log;
        $this->purchaseQuoteDashboardService = $purchaseQuoteDashboardService;
    }

    public function infoConta(Request $request)
    {
        $companyId = $request->header('company-id');

        // Inicialização dos acumuladores
        $totais = [
            'total_clientes' => Client::where('company_id', $companyId)->count(),
            'total_emprestimos' => 0,
            'total_emprestimos_atrasados' => 0,
            'total_emprestimos_pagos' => 0,
            'total_emprestimos_vencidos' => 0,
            'total_emprestimos_em_dias' => 0,
            'total_emprestimos_muito_atrasados' => 0,
            'total_ja_recebido' => 0,
            'total_ja_investido' => 0,
            'total_a_receber' => 0,
        ];

        // Processa os empréstimos em blocos para evitar estouro de memória
        Emprestimo::where('company_id', $companyId)
            ->select(['id', 'valor'])
            ->with(['parcelas' => function ($q) {
                $q->select(['id', 'emprestimo_id', 'valor']); // corrigido aqui
            }])
            ->chunk(100, function ($emprestimos) use (&$totais) {
                foreach ($emprestimos as $emprestimo) {
                    $parcela = $emprestimo->parcelas->first();

                    if ($parcela && method_exists($parcela, 'totalPendente')) {
                        $totais['total_a_receber'] += $parcela->totalPendente();
                    }

                    $totais['total_ja_investido'] += $emprestimo->valor;
                    $totais['total_ja_recebido'] += $emprestimo->total_pago;
                    $totais['total_emprestimos']++;

                    $status = $this->getStatus($emprestimo);

                    match ($status) {
                        'Atrasado' => $totais['total_emprestimos_atrasados']++,
                        'Pago' => $totais['total_emprestimos_pagos']++,
                        'Vencido' => $totais['total_emprestimos_vencidos']++,
                        'Em Dias' => $totais['total_emprestimos_em_dias']++,
                        'Muito Atrasado' => $totais['total_emprestimos_muito_atrasados']++,
                        default => null
                    };
                }
            });

        return $totais;
    }

    public function purchaseQuoteMetrics(Request $request)
    {
        $companyId = $request->header('company-id');
        $metrics = $this->purchaseQuoteDashboardService->getMetrics($companyId ? (int) $companyId : null);

        return response()->json($metrics);
    }


    private function getStatus($emprestimo)
    {
        $status = 'Em Dias'; // Padrão
        $qtParcelas = count($emprestimo->parcelas);
        $qtPagas = 0;
        $qtAtrasadas = 0;

        foreach ($emprestimo->parcelas as $parcela) {
            if ($parcela->atrasadas > 0 && $parcela->saldo > 0) {
                $qtAtrasadas++;
            }
        }

        if ($qtAtrasadas > 0) {
            $status = 'Muito Atrasado';

            if ($qtAtrasadas == $qtParcelas) {
                $status = 'Vencido';
            }
        }

        foreach ($emprestimo->parcelas as $parcela) {
            if ($parcela->dt_baixa != null) {
                $qtPagas++;
            }
        }

        if ($qtParcelas == $qtPagas) {
            $status = 'Pago';
        }

        return $status;
    }

    private function isMaiorQuatro($qtAtrasadas, $qtParcelas)
    {
        return $qtAtrasadas > 4;
    }


}

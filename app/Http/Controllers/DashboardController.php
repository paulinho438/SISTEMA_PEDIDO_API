<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomLog;
use App\Models\Emprestimo;
use App\Services\PurchaseQuote\PurchaseQuoteDashboardService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
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

    public function stockMetrics(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_dashboard')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar o dashboard de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $companyId = $request->header('company-id');
        
        // Buscar produtos com estoque total calculado
        $products = \App\Models\StockProduct::where('stock_products.company_id', $companyId)
            ->where('stock_products.active', true)
            ->leftJoin('stocks', 'stock_products.id', '=', 'stocks.stock_product_id')
            ->select(
                'stock_products.id',
                'stock_products.code',
                'stock_products.description',
                'stock_products.unit',
                'stock_products.min_stock',
                'stock_products.max_stock',
                \DB::raw('COALESCE(SUM(stocks.quantity_available), 0) as total_available')
            )
            ->groupBy('stock_products.id', 'stock_products.code', 'stock_products.description', 'stock_products.unit', 'stock_products.min_stock', 'stock_products.max_stock')
            ->get();

        $totalProducts = $products->count();
        $lowStockProducts = [];
        $outOfStockProducts = [];
        $totalValue = 0;

        foreach ($products as $product) {
            $totalAvailable = (float) $product->total_available;
            
            // Verificar se está abaixo do mínimo (ou se não tem mínimo definido mas está zerado)
            if (($product->min_stock !== null && $totalAvailable <= $product->min_stock) || 
                ($product->min_stock === null && $totalAvailable == 0)) {
                $percentage = $product->min_stock > 0 ? ($totalAvailable / $product->min_stock) * 100 : 0;
                
                $productData = [
                    'id' => $product->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'min_stock' => $product->min_stock,
                    'max_stock' => $product->max_stock,
                    'current_stock' => $totalAvailable,
                    'percentage' => round($percentage, 2),
                    'unit' => $product->unit,
                ];

                if ($totalAvailable == 0) {
                    $outOfStockProducts[] = $productData;
                } else {
                    $lowStockProducts[] = $productData;
                }
            }
        }

        // Ordenar por porcentagem (menor primeiro)
        usort($lowStockProducts, function($a, $b) {
            return $a['percentage'] <=> $b['percentage'];
        });

        // Buscar últimas 10 movimentações
        $recentMovements = \App\Models\StockMovement::where('stock_movements.company_id', $companyId)
            ->with(['product', 'location', 'user'])
            ->orderBy('stock_movements.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($movement) {
                return [
                    'id' => $movement->id,
                    'movement_date' => $movement->movement_date ? $movement->movement_date->format('Y-m-d') : ($movement->created_at ? \Carbon\Carbon::parse($movement->created_at)->format('Y-m-d') : null),
                    'product_code' => $movement->product->code ?? null,
                    'product_description' => $movement->product->description ?? null,
                    'location_name' => $movement->location->name ?? null,
                    'movement_type' => $movement->movement_type,
                    'quantity' => $movement->quantity,
                    'observation' => $movement->observation,
                    'user_name' => $movement->user->nome_completo ?? null,
                ];
            });

        return response()->json([
            'total_products' => $totalProducts,
            'low_stock_products' => $lowStockProducts,
            'out_of_stock_products' => $outOfStockProducts,
            'total_low_stock' => count($lowStockProducts) + count($outOfStockProducts),
            'total_value' => $totalValue,
            'recent_movements' => $recentMovements,
        ]);
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

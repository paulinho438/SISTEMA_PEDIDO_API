<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CustomLog;
use App\Models\Emprestimo;
use App\Models\StockMovement;
use App\Services\PurchaseQuote\PurchaseQuoteDashboardService;
use App\Services\StockAccessService;
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

        $companyId = (int) $request->header('company-id');
        $accessService = new StockAccessService();
        $accessibleLocationIds = array_map('intval', $accessService->getAccessibleLocationIds($user, $companyId));

        // Almoxarife sem locais associados ou usuário sem acesso: retorna métricas vazias
        if (empty($accessibleLocationIds)) {
            return response()->json([
                'total_products' => 0,
                'low_stock_products' => [],
                'out_of_stock_products' => [],
                'total_low_stock' => 0,
                'total_value' => 0,
                'recent_movements' => [],
            ]);
        }

        // Buscar produtos ativos
        // IMPORTANTE: O estoque mínimo é verificado por LOCAL, não pela soma.
        // Se min_stock = 5, CADA local precisa ter pelo menos 5 unidades.
        // Se qualquer local tiver menos que o mínimo, o produto aparece como estoque baixo.
        $products = \App\Models\StockProduct::where('stock_products.company_id', $companyId)
            ->where('stock_products.active', true)
            ->with(['stocks.location'])
            ->get();

        // Almoxarife: considerar apenas estoques nos locais a que tem acesso
        foreach ($products as $product) {
            $product->setRelation('stocks', $product->stocks->filter(
                fn ($s) => in_array((int) $s->stock_location_id, $accessibleLocationIds)
            ));
        }

        // Produtos que têm pelo menos um estoque em algum local acessível
        $products = $products->filter(fn ($p) => $p->stocks->isNotEmpty());

        $totalProducts = $products->count();
        $lowStockProducts = [];
        $outOfStockProducts = [];
        $totalValue = 0;

        foreach ($products as $product) {
            // Se não tem mínimo definido, verificar apenas se está zerado em todos os locais
            if ($product->min_stock === null) {
                $hasAnyStock = $product->stocks->sum('quantity_available') > 0;
                if (!$hasAnyStock) {
                    $outOfStockProducts[] = [
                        'id' => $product->id,
                        'code' => $product->code,
                        'description' => $product->description,
                        'min_stock' => $product->min_stock,
                        'max_stock' => $product->max_stock,
                        'current_stock' => 0,
                        'percentage' => 0,
                        'unit' => $product->unit,
                        'low_locations' => [],
                    ];
                }
                continue;
            }

            // Verificar se algum local tem estoque abaixo do mínimo
            $lowLocations = [];
            $hasLowStock = false;
            $hasOutOfStock = false;
            $minCurrentStock = null; // Menor estoque encontrado entre os locais

            // Se não tem nenhum estoque cadastrado, considerar como sem estoque
            if ($product->stocks->isEmpty()) {
                $hasOutOfStock = true;
                $hasLowStock = true;
                $minCurrentStock = 0;
            } else {
                // Verificar cada local individualmente
                foreach ($product->stocks as $stock) {
                    $quantityAvailable = (float) $stock->quantity_available;
                    
                    // Guardar o menor estoque encontrado
                    if ($minCurrentStock === null || $quantityAvailable < $minCurrentStock) {
                        $minCurrentStock = $quantityAvailable;
                    }

                    // Se este local está abaixo ou igual ao mínimo, adicionar à lista
                    if ($quantityAvailable <= $product->min_stock) {
                        $hasLowStock = true;
                        $lowLocations[] = [
                            'location_id' => $stock->stock_location_id,
                            'location_name' => $stock->location->name ?? 'Local não encontrado',
                            'quantity' => $quantityAvailable,
                        ];

                        // Se está zerado, marca como sem estoque
                        if ($quantityAvailable == 0) {
                            $hasOutOfStock = true;
                        }
                    }
                }
            }

            // Se algum local está abaixo do mínimo, adicionar à lista
            if ($hasLowStock) {
                $percentage = $product->min_stock > 0 && $minCurrentStock !== null 
                    ? ($minCurrentStock / $product->min_stock) * 100 
                    : 0;
                
                $productData = [
                    'id' => $product->id,
                    'code' => $product->code,
                    'description' => $product->description,
                    'min_stock' => $product->min_stock,
                    'max_stock' => $product->max_stock,
                    'current_stock' => $minCurrentStock ?? 0, // Menor estoque entre os locais
                    'percentage' => round($percentage, 2),
                    'unit' => $product->unit,
                    'low_locations' => $lowLocations, // Lista de locais abaixo do mínimo
                ];

                if ($hasOutOfStock || ($minCurrentStock !== null && $minCurrentStock == 0)) {
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

        // Buscar últimas 10 movimentações (almoxarife: apenas dos locais a que tem acesso)
        $recentMovements = StockMovement::where('stock_movements.company_id', $companyId)
            ->whereIn('stock_movements.stock_location_id', $accessibleLocationIds)
            ->with(['product', 'location', 'user'])
            ->orderBy('stock_movements.created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($movement) {
                $movementDate = $movement->movement_date 
                    ? \Carbon\Carbon::parse($movement->movement_date)->format('Y-m-d')
                    : ($movement->created_at ? \Carbon\Carbon::parse($movement->created_at)->format('Y-m-d') : null);
                
                return [
                    'id' => $movement->id,
                    'movement_date' => $movementDate,
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

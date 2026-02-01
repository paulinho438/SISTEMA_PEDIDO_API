<?php

namespace App\Http\Controllers;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteItem;
use App\Models\PurchaseQuoteStatus;
use App\Models\PurchaseQuoteStatusHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected array $defaultStatuses = [
        'compra_em_andamento',
        'analisada',
        'analisada_aguardando',
        'analise_gerencia',
        'aprovado',
    ];

    protected array $quoteSummaryDefaultStatuses = [
        'cotacao',
        'compra_em_andamento',
        'finalizada',
        'analisada',
        'analisada_aguardando',
        'analise_gerencia',
        'aprovado',
    ];

    public function costsByCostCenter(Request $request)
    {
        $companyId = $request->header('company-id');
        $statusFilter = $this->normalizeStatusFilter($request->get('status'), $this->defaultStatuses);

        $statuses = $this->loadStatuses($statusFilter);

        $quotes = $this->fetchQuotesWithDetails($request, $companyId, $statusFilter)
            ->filter(function ($quote) {
                return $quote->items->contains(function ($item) {
                    return $item->cost_center_code !== null || $item->cost_center_description !== null;
                });
            });

        if ($quotes->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'statuses' => $statuses->map(function ($status) {
                        return [
                            'slug' => $status->slug,
                            'label' => $status->label,
                        ];
                    })->values(),
                ],
            ]);
        }

        $grouped = [];

        foreach ($quotes as $quote) {
            foreach ($quote->items as $item) {
                if ($item->cost_center_code === null && $item->cost_center_description === null) {
                    continue;
                }

                $total = $this->resolveItemTotal($quote, $item);

                if ($total <= 0) {
                    continue;
                }

                $key = $item->cost_center_code ?? ('sem-codigo-' . $item->id);

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'codigo' => $item->cost_center_code,
                        'descricao' => $item->cost_center_description,
                        'valor_total' => 0.0,
                        'total_itens' => 0,
                        'cotacao_ids' => [],
                        'status' => [],
                    ];
                }

                $grouped[$key]['valor_total'] += $total;
                $grouped[$key]['total_itens']++;
                $grouped[$key]['cotacao_ids'][$quote->id] = true;

                $slug = $quote->current_status_slug;

                if (!isset($grouped[$key]['status'][$slug])) {
                    $grouped[$key]['status'][$slug] = [
                        'valor' => 0.0,
                        'itens' => 0,
                        'cotacoes' => [],
                    ];
                }

                $grouped[$key]['status'][$slug]['valor'] += $total;
                $grouped[$key]['status'][$slug]['itens']++;
                $grouped[$key]['status'][$slug]['cotacoes'][$quote->id] = true;
            }
        }

        $result = [];

        foreach ($grouped as $key => $entry) {
            $statusBreakdown = [];

            foreach ($entry['status'] as $slug => $statusData) {
                $label = $statuses[$slug]->label ?? ucfirst(str_replace('_', ' ', $slug));

                $statusBreakdown[] = [
                    'status' => $slug,
                    'label' => $label,
                    'valor' => round($statusData['valor'], 2),
                    'valor_formatado' => $this->formatCurrency($statusData['valor']),
                    'total_itens' => $statusData['itens'],
                    'total_cotacoes' => count($statusData['cotacoes']),
                ];
            }

            $result[] = [
                'id' => $key,
                'codigo' => $entry['codigo'],
                'descricao' => $entry['descricao'],
                'valor_total' => round($entry['valor_total'], 2),
                'valor_total_formatado' => $this->formatCurrency($entry['valor_total']),
                'total_itens' => $entry['total_itens'],
                'total_cotacoes' => count($entry['cotacao_ids']),
                'status_breakdown' => $statusBreakdown,
            ];
        }

        usort($result, static function ($a, $b) {
            return ($b['valor_total'] <=> $a['valor_total']) ?: strcmp($a['codigo'] ?? '', $b['codigo'] ?? '');
        });

        return response()->json([
            'data' => $result,
            'meta' => [
                'statuses' => $this->statusesToMeta($statuses),
            ],
        ]);
    }

    /**
     * Relatório detalhado por centro de custo: uma linha por item com código centro, descrição, fornecedor, valor e data da solicitação.
     * Permite exportação para Excel (CSV).
     */
    public function costsByCostCenterDetalhado(Request $request)
    {
        $companyId = $request->header('company-id');
        $statusFilter = $this->normalizeStatusFilter($request->get('status'), $this->defaultStatuses);

        $quotes = $this->fetchQuotesWithDetails($request, $companyId, $statusFilter)
            ->filter(function ($quote) {
                return $quote->items->contains(function ($item) {
                    return $item->cost_center_code !== null || $item->cost_center_description !== null;
                });
            });

        $result = [];

        foreach ($quotes as $quote) {
            $dataCriacao = $quote->requested_at
                ? Carbon::parse($quote->requested_at)->format('d/m/Y')
                : '-';

            foreach ($quote->items as $item) {
                if ($item->cost_center_code === null && $item->cost_center_description === null) {
                    continue;
                }

                $valor = $this->resolveItemTotal($quote, $item);
                $fornecedor = '-';
                if ($item->selectedSupplier && $item->selectedSupplier->supplier_name) {
                    $fornecedor = $item->selectedSupplier->supplier_name;
                }

                $result[] = [
                    'codigo_centro_custo' => $item->cost_center_code ?? '',
                    'descricao_centro_custo' => $item->cost_center_description ?? '',
                    'fornecedor' => $fornecedor,
                    'valor' => round($valor, 2),
                    'valor_formatado' => $this->formatCurrency($valor),
                    'data_criacao_solicitacao' => $dataCriacao,
                ];
            }
        }

        usort($result, static function ($a, $b) {
            $cmpCodigo = strcmp($a['codigo_centro_custo'] ?? '', $b['codigo_centro_custo'] ?? '');
            if ($cmpCodigo !== 0) {
                return $cmpCodigo;
            }
            return ($b['valor'] <=> $a['valor']);
        });

        return response()->json([
            'data' => $result,
            'meta' => [
                'statuses' => $this->statusesToMeta($this->loadStatuses($statusFilter)),
            ],
        ]);
    }

    public function quotesSummary(Request $request)
    {
        $companyId = $request->header('company-id');
        $statusFilter = $this->normalizeStatusFilter(
            $request->get('status'),
            $this->quoteSummaryDefaultStatuses
        );

        $statuses = $this->loadStatuses($statusFilter);

        $quotes = $this->fetchQuotesWithDetails($request, $companyId, $statusFilter);

        $result = $quotes->map(function (PurchaseQuote $quote) use ($statuses) {
            $total = 0.0;
            $totalItens = 0;

            foreach ($quote->items as $item) {
                $itemTotal = $this->resolveItemTotal($quote, $item);

                if ($itemTotal <= 0) {
                    continue;
                }

                $total += $itemTotal;
                $totalItens++;
            }

            $finalizedAt = $this->resolveFinalizedAt($quote);

            $selectedSupplier = null;

            if ($quote->items->contains(fn ($item) => $item->selectedSupplier !== null)) {
                $firstSelected = $quote->items->first(fn ($item) => $item->selectedSupplier !== null);

                if ($firstSelected?->selectedSupplier) {
                    $selectedSupplierTotal = $quote->items
                        ->filter(fn ($item) => $item->selectedSupplier && $item->selectedSupplier->id === $firstSelected->selectedSupplier->id)
                        ->reduce(function ($carry, $item) use ($quote) {
                            $carry += $this->resolveItemTotal($quote, $item);
                            return $carry;
                        }, 0.0);

                    $selectedSupplier = [
                        'id' => $firstSelected->selectedSupplier->id,
                        'nome' => $firstSelected->selectedSupplier->supplier_name,
                        'valor_total' => round($selectedSupplierTotal, 2),
                        'valor_total_formatado' => $this->formatCurrency($selectedSupplierTotal),
                    ];
                }
            }

            return [
                'id' => $quote->id,
                'numero' => $quote->quote_number,
                'status' => [
                    'slug' => $quote->current_status_slug,
                    'label' => $statuses[$quote->current_status_slug]->label ?? ucfirst($quote->current_status_slug),
                ],
                'comprador' => $quote->buyer_name,
                'empresa' => $quote->company_name,
                'centro_custo_principal' => [
                    'codigo' => $quote->main_cost_center_code,
                    'descricao' => $quote->main_cost_center_description,
                ],
                'data_finalizada' => $finalizedAt?->toDateString(),
                'data_finalizada_formatado' => $finalizedAt?->format('d/m/Y H:i'),
                'valor_total' => round($total, 2),
                'valor_total_formatado' => $this->formatCurrency($total),
                'total_itens' => $totalItens,
                'fornecedor_selecionado' => $selectedSupplier,
            ];
        });

        return response()->json([
            'data' => $result,
            'meta' => [
                'statuses' => $this->statusesToMeta($statuses),
            ],
        ]);
    }

    public function costsBySupplier(Request $request)
    {
        $companyId = $request->header('company-id');
        $statusFilter = $this->normalizeStatusFilter($request->get('status'), $this->defaultStatuses);
        $statuses = $this->loadStatuses($statusFilter);

        $quotes = $this->fetchQuotesWithDetails($request, $companyId, $statusFilter)
            ->filter(fn ($quote) => $quote->suppliers->isNotEmpty());

        $grouped = [];

        foreach ($quotes as $quote) {
            foreach ($quote->suppliers as $supplier) {
                $key = $supplier->id ?? ($supplier->supplier_name ?? 'sem-nome');

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id' => $supplier->id,
                        'nome' => $supplier->supplier_name,
                        'quantidade_cotacoes' => 0,
                        'quantidade_itens' => 0,
                        'valor_total' => 0.0,
                        'status' => [],
                    ];
                }

                $cotacaoValor = 0.0;
                $cotacaoItens = 0;

                foreach ($quote->items as $item) {
                    $supplierItem = $supplier->items
                        ->firstWhere('purchase_quote_item_id', $item->id);

                    if (!$supplierItem) {
                        continue;
                    }

                    $valorItem = $this->resolveSupplierItemTotal($supplierItem, $item->quantity ?? 0);

                    if ($valorItem <= 0) {
                        continue;
                    }

                    $cotacaoValor += $valorItem;
                    $cotacaoItens++;
                }

                if ($cotacaoValor <= 0) {
                    continue;
                }

                $grouped[$key]['valor_total'] += $cotacaoValor;
                $grouped[$key]['quantidade_itens'] += $cotacaoItens;
                $grouped[$key]['quantidade_cotacoes']++;

                $slug = $quote->current_status_slug;

                if (!isset($grouped[$key]['status'][$slug])) {
                    $grouped[$key]['status'][$slug] = [
                        'valor' => 0.0,
                        'cotacoes' => 0,
                    ];
                }

                $grouped[$key]['status'][$slug]['valor'] += $cotacaoValor;
                $grouped[$key]['status'][$slug]['cotacoes']++;
            }
        }

        $result = array_map(function ($entry) use ($statuses) {
            $statusBreakdown = [];

            foreach ($entry['status'] as $slug => $statusData) {
                $label = $statuses[$slug]->label ?? ucfirst($slug);

                $statusBreakdown[] = [
                    'status' => $slug,
                    'label' => $label,
                    'valor' => round($statusData['valor'], 2),
                    'valor_formatado' => $this->formatCurrency($statusData['valor']),
                    'total_cotacoes' => $statusData['cotacoes'],
                ];
            }

            return [
                'id' => $entry['id'],
                'nome' => $entry['nome'] ?? 'Fornecedor não informado',
                'valor_total' => round($entry['valor_total'], 2),
                'valor_total_formatado' => $this->formatCurrency($entry['valor_total']),
                'total_cotacoes' => $entry['quantidade_cotacoes'],
                'total_itens' => $entry['quantidade_itens'],
                'status_breakdown' => $statusBreakdown,
            ];
        }, $grouped);

        usort($result, static function ($a, $b) {
            return ($b['valor_total'] <=> $a['valor_total']) ?: strcmp($a['nome'], $b['nome']);
        });

        return response()->json([
            'data' => $result,
            'meta' => [
                'statuses' => $this->statusesToMeta($statuses),
            ],
        ]);
    }

    public function costsByQuote(Request $request)
    {
        $companyId = $request->header('company-id');
        $statusFilter = $this->normalizeStatusFilter(
            $request->get('status'),
            $this->quoteSummaryDefaultStatuses
        );
        $statuses = $this->loadStatuses($statusFilter);

        $quotes = $this->fetchQuotesWithDetails($request, $companyId, $statusFilter);

        $result = $quotes->map(function (PurchaseQuote $quote) use ($statuses) {
            $total = 0.0;
            $totalItens = 0;

            foreach ($quote->items as $item) {
                $itemTotal = $this->resolveItemTotal($quote, $item);

                if ($itemTotal <= 0) {
                    continue;
                }

                $total += $itemTotal;
                $totalItens++;
            }

            $suppliers = $quote->suppliers->map(function ($supplier) use ($quote) {
                $valor = 0.0;

                foreach ($quote->items as $item) {
                    $supplierItem = $supplier->items
                        ->firstWhere('purchase_quote_item_id', $item->id);

                    if (!$supplierItem) {
                        continue;
                    }

                    $valor += $this->resolveSupplierItemTotal($supplierItem, $item->quantity ?? 0);
                }

                return [
                    'id' => $supplier->id,
                    'nome' => $supplier->supplier_name,
                    'valor' => round($valor, 2),
                    'valor_formatado' => $this->formatCurrency($valor),
                ];
            })->filter(fn ($supplier) => $supplier['valor'] > 0)->values();

            return [
                'id' => $quote->id,
                'numero' => $quote->quote_number,
                'status' => [
                    'slug' => $quote->current_status_slug,
                    'label' => $statuses[$quote->current_status_slug]->label ?? ucfirst($quote->current_status_slug),
                ],
                'empresa' => $quote->company_name,
                'comprador' => $quote->buyer_name,
                'valor_total' => round($total, 2),
                'valor_total_formatado' => $this->formatCurrency($total),
                'total_itens' => $totalItens,
                'fornecedores' => $suppliers,
            ];
        });

        return response()->json([
            'data' => $result,
            'meta' => [
                'statuses' => $this->statusesToMeta($statuses),
            ],
        ]);
    }

    public function historyByPeriod(Request $request)
    {
        $companyId = $request->header('company-id');
        $statusFilter = $this->normalizeStatusFilter(
            $request->get('status'),
            ['finalizada', 'analisada', 'aprovado']
        );

        $start = $request->get('start_date');
        $end = $request->get('end_date');

        // Parse das datas como data local (sem timezone) para evitar problemas de conversão
        $startDate = $start ? Carbon::createFromFormat('Y-m-d', $start)->startOfDay() : null;
        $endDate = $end ? Carbon::createFromFormat('Y-m-d', $end)->endOfDay() : null;

        $historyRecords = PurchaseQuoteStatusHistory::query()
            ->select([
                'purchase_quote_status_histories.purchase_quote_id',
                'purchase_quote_status_histories.status_slug',
                'purchase_quote_status_histories.acted_at',
            ])
            ->leftJoin('purchase_quotes', 'purchase_quotes.id', '=', 'purchase_quote_status_histories.purchase_quote_id')
            ->whereIn('purchase_quote_status_histories.status_slug', $statusFilter)
            ->when($startDate, fn ($query) => $query->whereRaw('CAST([purchase_quote_status_histories].[acted_at] AS DATETIME2) >= CAST(? AS DATETIME2)', [$startDate->format('Y-m-d H:i:s')]))
            ->when($endDate, fn ($query) => $query->whereRaw('CAST([purchase_quote_status_histories].[acted_at] AS DATETIME2) <= CAST(? AS DATETIME2)', [$endDate->format('Y-m-d H:i:s')]))
            ->when($companyId, function ($query) use ($companyId) {
                $query->where(function ($builder) use ($companyId) {
                    $builder
                        ->where('purchase_quotes.company_id', $companyId)
                        ->orWhereNull('purchase_quotes.company_id');
                });
            })
            ->orderByDesc('purchase_quote_status_histories.acted_at')
            ->get();

        if ($historyRecords->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'statuses' => $this->statusesToMeta($this->loadStatuses($statusFilter)),
                    'serie_total' => [],
                    'periodo' => [
                        'inicio' => $startDate ? $startDate->format('Y-m-d') : null,
                        'fim' => $endDate ? $endDate->format('Y-m-d') : null,
                    ],
                ],
            ]);
        }

        $latestByQuote = $historyRecords
            ->groupBy('purchase_quote_id')
            ->map(function ($items) {
                return $items->sortByDesc('acted_at')->first();
            });

        $statuses = $this->loadStatuses($statusFilter);

        $grouped = [];
        $totalsPorStatus = [];

        foreach ($latestByQuote as $record) {
            $date = Carbon::parse($record->acted_at)->format('Y-m-d');
            $slug = $record->status_slug;

            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'data' => $date,
                    'total' => 0,
                    'status' => [],
                ];
            }

            if (!isset($grouped[$date]['status'][$slug])) {
                $grouped[$date]['status'][$slug] = [
                    'status' => $slug,
                    'label' => $statuses[$slug]->label ?? ucfirst($slug),
                    'total' => 0,
                ];
            }

            $grouped[$date]['status'][$slug]['total']++;
            $grouped[$date]['total']++;

            $totalsPorStatus[$slug] = ($totalsPorStatus[$slug] ?? 0) + 1;
        }

        foreach ($grouped as &$entry) {
            $entry['status'] = array_values($entry['status']);
        }

        unset($entry);

        ksort($grouped);

        $serie = [];

        foreach ($statuses as $slug => $status) {
            $serie[] = [
                'status' => $slug,
                'label' => $status->label,
                'total' => $totalsPorStatus[$slug] ?? 0,
            ];
        }

        $periodoMeta = [
            'inicio' => $startDate?->format('Y-m-d'),
            'fim' => $endDate?->format('Y-m-d'),
        ];

        if (!$startDate || !$endDate) {
            if (!empty($grouped)) {
                $datas = array_keys($grouped);
                sort($datas);

                if (!$startDate) {
                    $periodoMeta['inicio'] = $datas[0];
                }

                if (!$endDate) {
                    $periodoMeta['fim'] = end($datas);
                }
            }
        }

        return response()->json([
            'data' => array_values($grouped),
            'meta' => [
                'statuses' => $this->statusesToMeta($statuses),
                'serie_total' => $serie,
                'periodo' => $periodoMeta,
            ],
        ]);
    }

    /**
     * Relatório Solicitação/Produto: retorna todas as solicitações (cotações) que contêm itens
     * que batem com o filtro de produto (código ou descrição). Filtros: período, empresa, produto.
     */
    public function solicitacaoProduto(Request $request)
    {
        $companyId = $request->header('company-id');
        $companyIdFilter = $request->get('company_id') ? (int) $request->get('company_id') : $companyId;

        $start = $request->get('start_date');
        $end = $request->get('end_date');
        $productTerm = $request->get('product');
        $productTerm = is_string($productTerm) ? trim($productTerm) : '';

        $startDate = $start ? Carbon::parse($start)->startOfDay() : null;
        $endDate = $end ? Carbon::parse($end)->endOfDay() : null;

        if ($productTerm === '') {
            return response()->json([
                'data' => [],
                'meta' => ['total_qtd' => 0],
            ]);
        }

        $query = PurchaseQuote::query()
            ->with([
                'items' => function ($q) use ($productTerm) {
                    $q->select([
                        'id',
                        'purchase_quote_id',
                        'product_code',
                        'description',
                        'quantity',
                    ]);
                    if ($productTerm !== '') {
                        $term = '%' . strtoupper($productTerm) . '%';
                        $q->where(function ($q2) use ($term) {
                            $q2->whereRaw('UPPER(description) LIKE ?', [$term])
                                ->orWhereRaw('UPPER(product_code) LIKE ?', [$term]);
                        });
                    }
                },
            ])
            ->when($startDate, fn ($q) => $q->where('requested_at', '>=', $startDate->format('Y-m-d H:i:s')))
            ->when($endDate, fn ($q) => $q->where('requested_at', '<=', $endDate->format('Y-m-d H:i:s')))
            ->when($companyIdFilter, function ($q) use ($companyIdFilter) {
                $q->where(function ($builder) use ($companyIdFilter) {
                    $builder->where('company_id', $companyIdFilter)->orWhereNull('company_id');
                });
            })
            ->orderByDesc('requested_at');

        $quotes = $query->get();

        $result = [];

        foreach ($quotes as $quote) {
            $items = $quote->items;

            if ($productTerm !== '') {
                $items = $items->filter(function ($item) use ($productTerm) {
                    $term = strtoupper($productTerm);
                    $desc = strtoupper($item->description ?? '');
                    $code = strtoupper($item->product_code ?? '');
                    return str_contains($desc, $term) || str_contains($code, $term);
                });
            }

            if ($items->isEmpty()) {
                continue;
            }

            $dataSolicitacao = $quote->requested_at
                ? Carbon::parse($quote->requested_at)->format('d/m/Y')
                : '-';
            $obs = $quote->observation ?? '';

            foreach ($items as $item) {
                $result[] = [
                    'cotacao_id' => $quote->id,
                    'numero' => $quote->quote_number,
                    'data' => $dataSolicitacao,
                    'produto' => $item->description ?? $item->product_code ?? '-',
                    'qtd' => (int) ($item->quantity ?? 0),
                    'obs' => $obs,
                ];
            }
        }

        return response()->json([
            'data' => $result,
            'meta' => [
                'total_qtd' => array_sum(array_column($result, 'qtd')),
            ],
        ]);
    }

    protected function normalizeStatusFilter(?string $filter, ?array $fallback = null): array
    {
        if ($filter === null || trim($filter) === '') {
            return $fallback ?? $this->defaultStatuses;
        }

        $parts = collect(explode(',', $filter))
            ->map(fn ($slug) => trim($slug))
            ->filter();

        $validated = $parts->intersect($this->allowedStatuses());

        if ($validated->isEmpty()) {
            return $fallback ?? $this->defaultStatuses;
        }

        return $validated->unique()->values()->all();
    }

    protected function allowedStatuses(): array
    {
        static $statuses;

        if ($statuses === null) {
            $statuses = PurchaseQuoteStatus::query()->pluck('slug')->all();
        }

        return $statuses;
    }

    protected function applyDateFilters(Request $request, $query): void
    {
        $start = $request->get('start_date');
        $end = $request->get('end_date');

        if (!$start && !$end) {
            return;
        }

        try {
            $startDate = $start ? Carbon::parse($start)->startOfDay() : null;
            $endDate = $end ? Carbon::parse($end)->endOfDay() : null;
        } catch (\Throwable $exception) {
            return;
        }

        $query->where(function ($outer) use ($startDate, $endDate) {
            $outer->whereHas('statusHistory', function ($history) use ($startDate, $endDate) {
                $history->where('status_slug', 'finalizada')
                    ->when($startDate, fn ($q) => $q->where('acted_at', '>=', $startDate))
                    ->when($endDate, fn ($q) => $q->where('acted_at', '<=', $endDate));
            });

            $outer->orWhere(function ($fallback) use ($startDate, $endDate) {
                if ($startDate) {
                    $fallback->where('requested_at', '>=', $startDate);
                }

                if ($endDate) {
                    if ($startDate) {
                        $fallback->where('requested_at', '<=', $endDate);
                    } else {
                        $fallback->where('requested_at', '<=', $endDate);
                    }
                }
            });
        });
    }

    protected function resolveItemTotal(PurchaseQuote $quote, PurchaseQuoteItem $item): float
    {
        $quantity = $item->quantity ?? 0;

        if ($item->selected_total_cost !== null) {
            return (float) $item->selected_total_cost;
        }

        if ($item->selected_unit_cost !== null) {
            return (float) ($item->selected_unit_cost * $quantity);
        }

        $supplierItem = null;

        if ($item->selectedSupplier && $item->selectedSupplier->items) {
            $supplierItem = $item->selectedSupplier->items
                ->firstWhere('purchase_quote_item_id', $item->id);
        }

        if (!$supplierItem) {
            $supplierItem = $quote->suppliers
                ->map(fn ($supplier) => $supplier->items->firstWhere('purchase_quote_item_id', $item->id))
                ->filter()
                ->sortBy(function ($supplierItem) {
                    if ($supplierItem?->final_cost !== null) {
                        return $supplierItem->final_cost;
                    }

                    return $supplierItem?->unit_cost ?? INF;
                })
                ->first();
        }

        if ($supplierItem) {
            if ($supplierItem->final_cost !== null) {
                return (float) $supplierItem->final_cost;
            }

            if ($supplierItem->unit_cost !== null) {
                return (float) ($supplierItem->unit_cost * $quantity);
            }
        }

        return 0.0;
    }

    protected function resolveSupplierItemTotal($supplierItem, float $quantity): float
    {
        if ($supplierItem->final_cost !== null) {
            return (float) $supplierItem->final_cost;
        }

        if ($supplierItem->unit_cost !== null) {
            return (float) ($supplierItem->unit_cost * $quantity);
        }

        return 0.0;
    }

    protected function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    protected function fetchQuotesWithDetails(Request $request, ?int $companyId, array $statusFilter)
    {
        $query = PurchaseQuote::query()
            ->with([
                'items' => function ($query) {
                    $query->select([
                        'id',
                        'purchase_quote_id',
                        'description',
                        'quantity',
                        'cost_center_code',
                        'cost_center_description',
                        'selected_supplier_id',
                        'selected_total_cost',
                        'selected_unit_cost',
                    ]);
                },
                'items.selectedSupplier:id,purchase_quote_id,supplier_name',
                'items.selectedSupplier.items:id,purchase_quote_supplier_id,purchase_quote_item_id,unit_cost,final_cost',
                'suppliers:id,purchase_quote_id,supplier_name',
                'suppliers.items:id,purchase_quote_supplier_id,purchase_quote_item_id,unit_cost,final_cost',
                'statusHistory' => function ($history) {
                    $history->where('status_slug', 'finalizada')
                        ->orderByDesc('acted_at');
                },
            ])
            ->whereIn('current_status_slug', $statusFilter)
            ->when($companyId, function ($query) use ($companyId) {
                $query->where(function ($builder) use ($companyId) {
                    $builder
                        ->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                });
            });

        $this->applyDateFilters($request, $query);

        return $query->get();
    }

    protected function statusesToMeta($statuses): array
    {
        return $statuses->map(function ($status) {
            return [
                'slug' => $status->slug,
                'label' => $status->label,
            ];
        })->values()->all();
    }

    protected function loadStatuses(array $slugs)
    {
        return PurchaseQuoteStatus::query()
            ->whereIn('slug', $slugs)
            ->orderBy('order')
            ->get()
            ->keyBy('slug');
    }

    protected function resolveFinalizedAt(PurchaseQuote $quote): ?Carbon
    {
        $history = $quote->statusHistory->first();

        if ($history && $history->acted_at) {
            return Carbon::parse($history->acted_at);
        }

        return null;
    }
}



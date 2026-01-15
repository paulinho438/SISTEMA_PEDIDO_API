<?php

namespace App\Services\PurchaseQuote;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteStatus;
use App\Models\PurchaseQuoteStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseQuoteDashboardService
{
    /**
     * Statuses that represent the approval flow after the buyer finalizes the quote.
     *
     * @var array<int, string>
     */
    protected array $finalStatuses = [
        'finalizada',
        'analisada',
        'analisada_aguardando',
        'analise_gerencia',
        'aprovado',
    ];

    /**
     * Builds the dashboard metrics for purchase quotes handled by buyers.
     */
    public function getMetrics(?int $companyId = null): array
    {
        setlocale(LC_TIME, 'pt_BR.UTF-8');
        Carbon::setLocale('pt_BR');

        $buyers = $this->fetchBuyers($companyId);

        if ($buyers->isEmpty()) {
            return [
                'processos' => [],
                'meta' => ['monthly_columns' => []],
                'status_por_comprador' => [],
                'status_resumo' => [],
                'media_mensal' => ['labels' => [], 'values' => []],
            ];
        }

        $monthlyColumns = $this->buildMonthlyColumns();
        $finalizadaRecords = $this->fetchFinalizadaRecords($companyId);

        $processos = $this->computeBuyerMetrics($buyers, $finalizadaRecords, $monthlyColumns);
        $statusPorComprador = $this->computeStatusPorComprador($buyers, $companyId);
        $statusResumo = $this->computeStatusResumo($companyId);
        $lineDataset = $this->buildLineDataset($finalizadaRecords);

        return [
            'processos' => array_values($processos),
            'meta' => [
                'monthly_columns' => array_map(
                    fn (array $column) => [
                        'key' => $column['key'],
                        'label' => ucfirst($column['label']),
                    ],
                    $monthlyColumns
                ),
            ],
            'status_por_comprador' => array_values($statusPorComprador),
            'status_resumo' => array_values($statusResumo),
            'media_mensal' => $lineDataset,
        ];
    }

    /**
     * Lista compradores vinculados à empresa (quando informado).
     */
    protected function fetchBuyers(?int $companyId): Collection
    {
        return User::query()
            ->select(['id', 'nome_completo', 'login'])
            ->whereHas('groups', function ($query) use ($companyId) {
                if ($companyId) {
                    $query->where('company_id', $companyId);
                }

                $query->where(function ($groupQuery) {
                    $groupQuery->where('name', 'LIKE', '%comprador%')
                        ->orWhereHas('items', function ($itemQuery) {
                            $itemQuery->where('slug', 'LIKE', '%comprador%');
                        });
                });
            })
            ->orderBy('nome_completo')
            ->get();
    }

    /**
     * Monta a definição das colunas de médias mensais (mês atual e anterior).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildMonthlyColumns(int $count = 2): array
    {
        $columns = [];

        for ($index = $count - 1; $index >= 0; $index--) {
            $month = Carbon::now()->copy()->subMonths($index)->startOfMonth();

            $columns[] = [
                'key' => 'media_mes_' . ($count - $index),
                'label' => $month->isoFormat('MMMM'),
                'start' => $month->copy(),
                'end' => $month->copy()->endOfMonth(),
                'days' => $month->daysInMonth,
            ];
        }

        return $columns;
    }

    /**
     * Recupera o histórico de cotações finalizadas.
     */
    protected function fetchFinalizadaRecords(?int $companyId): Collection
    {
        $latestFinalizations = PurchaseQuoteStatusHistory::query()
            ->select([
                'purchase_quote_id',
                DB::raw('MAX(acted_at) as acted_at'),
            ])
            ->where('status_slug', 'finalizada')
            ->groupBy('purchase_quote_id');

        return DB::query()
            ->fromSub($latestFinalizations, 'finalizadas')
            ->join('purchase_quotes', 'purchase_quotes.id', '=', 'finalizadas.purchase_quote_id')
            ->select([
                'purchase_quotes.buyer_id',
                'purchase_quotes.buyer_name',
                'finalizadas.acted_at',
            ])
            ->whereNotNull('purchase_quotes.buyer_id')
            ->when($companyId, function ($query) use ($companyId) {
                $query->where(function ($builder) use ($companyId) {
                    $builder
                        ->where('purchase_quotes.company_id', $companyId)
                        ->orWhereNull('purchase_quotes.company_id');
                });
            })
            ->get();
    }

    /**
     * Calcula os indicadores por comprador.
     */
    protected function computeBuyerMetrics(Collection $buyers, Collection $records, array $monthlyColumns): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = Carbon::now()->endOfWeek(Carbon::SUNDAY);
        $previousWeekStart = $weekStart->copy()->subWeek();
        $previousWeekEnd = $weekEnd->copy()->subWeek();
        $daysElapsedCurrentWeek = max(1, Carbon::now()->diffInDays($weekStart) + 1);

        $dayKeys = [
            1 => 'seg',
            2 => 'ter',
            3 => 'qua',
            4 => 'qui',
            5 => 'sex',
            6 => 'sab',
            7 => 'dom',
        ];

        $results = [];
        $monthlyCounts = [];

        foreach ($buyers as $buyer) {
            $name = $this->resolveBuyerName($buyer);

            $results[$buyer->id] = [
                'comprador' => $name,
                'acumulado' => 0,
                'semana_anterior' => 0,
                'semana_atual' => 0,
                'seg' => 0,
                'ter' => 0,
                'qua' => 0,
                'qui' => 0,
                'sex' => 0,
                'sab' => 0,
                'dom' => 0,
                'media_semana_atual' => 0,
                'media_semana_anterior' => 0,
            ];

            foreach ($monthlyColumns as $column) {
                $results[$buyer->id][$column['key']] = 0;
                $monthlyCounts[$buyer->id][$column['key']] = 0;
            }
        }

        foreach ($records as $record) {
            $buyerId = (int) $record->buyer_id;

            if (!isset($results[$buyerId])) {
                continue;
            }

            $results[$buyerId]['acumulado']++;
            $actedAt = Carbon::parse($record->acted_at);

            if ($actedAt->betweenIncluded($weekStart, $weekEnd)) {
                $results[$buyerId]['semana_atual']++;
                $dayKey = $dayKeys[$actedAt->dayOfWeekIso] ?? null;

                if ($dayKey) {
                    $results[$buyerId][$dayKey]++;
                }
            } elseif ($actedAt->betweenIncluded($previousWeekStart, $previousWeekEnd)) {
                $results[$buyerId]['semana_anterior']++;
            }

            foreach ($monthlyColumns as $column) {
                if ($actedAt->betweenIncluded($column['start'], $column['end'])) {
                    $monthlyCounts[$buyerId][$column['key']] =
                        ($monthlyCounts[$buyerId][$column['key']] ?? 0) + 1;
                }
            }
        }

        foreach ($results as $buyerId => &$data) {
            $data['media_semana_atual'] = $data['semana_atual'] > 0
                ? round($data['semana_atual'] / max($daysElapsedCurrentWeek, 1), 2)
                : 0;

            $data['media_semana_anterior'] = $data['semana_anterior'] > 0
                ? round($data['semana_anterior'] / 7, 2)
                : 0;

            foreach ($monthlyColumns as $column) {
                $count = $monthlyCounts[$buyerId][$column['key']] ?? 0;
                $data[$column['key']] = $count > 0
                    ? round($count / max($column['days'], 1), 2)
                    : 0;
            }
        }

        unset($data);

        return $results;
    }

    /**
     * Consolida as quantidades por status para cada comprador.
     */
    protected function computeStatusPorComprador(Collection $buyers, ?int $companyId = null): array
    {
        $counts = PurchaseQuote::query()
            ->select('buyer_id', 'current_status_slug', DB::raw('COUNT(*) as total'))
            ->whereNotNull('buyer_id')
            ->when($companyId, function ($query) use ($companyId) {
                $query->where(function ($builder) use ($companyId) {
                    $builder
                        ->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                });
            })
            ->groupBy('buyer_id', 'current_status_slug')
            ->get();

        $byBuyer = [];
        foreach ($counts as $row) {
            $byBuyer[$row->buyer_id][$row->current_status_slug] = (int) $row->total;
        }

        $result = [];
        $overallTotal = 0;

        foreach ($buyers as $buyer) {
            $buyerCounts = $byBuyer[$buyer->id] ?? [];

            $aguardando = (int) ($buyerCounts['compra_em_andamento'] ?? 0);

            $cotacao = 0;
            foreach ($this->finalStatuses as $status) {
                $cotacao += (int) ($buyerCounts[$status] ?? 0);
            }

            $total = $aguardando + $cotacao;

            if ($total === 0) {
                continue;
            }

            $overallTotal += $total;

            $result[$buyer->id] = [
                'comprador' => $this->resolveBuyerName($buyer),
                'aguardando' => $aguardando,
                'cotacao' => $cotacao,
                'total' => $total,
                'percentual' => 0,
            ];
        }

        if ($overallTotal > 0) {
            foreach ($result as &$row) {
                $row['percentual'] = round(($row['total'] / $overallTotal) * 100, 2);
            }
        }

        unset($row);

        return $result;
    }

    /**
     * Consolida contagem por status para o resumo geral.
     */
    protected function computeStatusResumo(?int $companyId = null): array
    {
        $relevantStatuses = array_merge(['compra_em_andamento'], $this->finalStatuses);

        $statusCounts = PurchaseQuote::query()
            ->select('current_status_slug', DB::raw('COUNT(*) as total'))
            ->whereNotNull('buyer_id')
            ->whereIn('current_status_slug', $relevantStatuses)
            ->when($companyId, function ($query) use ($companyId) {
                $query->where(function ($builder) use ($companyId) {
                    $builder
                        ->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                });
            })
            ->groupBy('current_status_slug')
            ->get();

        if ($statusCounts->isEmpty()) {
            return [];
        }

        $totalAll = $statusCounts->sum('total');

        $statuses = PurchaseQuoteStatus::query()
            ->whereIn('slug', $statusCounts->pluck('current_status_slug'))
            ->get()
            ->keyBy('slug');

        $summary = [];

        foreach ($statusCounts as $row) {
            $slug = $row->current_status_slug;
            $label = $statuses[$slug]->label ?? ucfirst(str_replace('_', ' ', $slug));

            $summary[] = [
                'status' => $label,
                'quantidade' => (int) $row->total,
                'percentual' => $totalAll > 0 ? round(($row->total / $totalAll) * 100, 2) : 0,
            ];
        }

        return $summary;
    }

    /**
     * Prepara dados agregados mensais para o gráfico.
     */
    protected function buildLineDataset(Collection $records, int $months = 4): array
    {
        $monthsMap = [];

        for ($index = $months - 1; $index >= 0; $index--) {
            $month = Carbon::now()->copy()->subMonths($index)->startOfMonth();
            $key = $month->format('Y-m');

            $monthsMap[$key] = [
                'label' => ucfirst($month->isoFormat('MMMM')),
                'count' => 0,
            ];
        }

        foreach ($records as $record) {
            $actedMonth = Carbon::parse($record->acted_at)->format('Y-m');

            if (isset($monthsMap[$actedMonth])) {
                $monthsMap[$actedMonth]['count']++;
            }
        }

        return [
            'labels' => array_column($monthsMap, 'label'),
            'values' => array_map(static fn ($item) => $item['count'], $monthsMap),
        ];
    }

    /**
     * Obtém um nome legível para o comprador.
     */
    protected function resolveBuyerName(User $buyer): string
    {
        $name = trim((string) $buyer->nome_completo);

        if ($name !== '') {
            return $name;
        }

        $login = trim((string) $buyer->login);

        return $login !== '' ? $login : 'Comprador';
    }
}



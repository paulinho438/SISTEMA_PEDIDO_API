<?php

namespace App\Http\Controllers;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteItem;
use App\Models\PurchaseQuoteStatus;
use App\Models\PurchaseQuoteSupplier;
use App\Models\PurchaseQuoteSupplierItem;
use App\Models\PurchaseQuoteStatusHistory;
use App\Models\PurchaseQuoteMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\Company;
use App\Services\PurchaseQuote\PurchaseQuoteProtheusExportService;
use App\Services\PurchaseQuote\PurchaseQuoteProductDefaultsService;
use App\Services\PurchaseQuote\PurchaseQuoteApprovalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;

class PurchaseQuoteController extends Controller
{
    /**
     * Helper para inserir registros com timestamps como strings (compatível com SQL Server)
     */
    private function insertWithStringTimestamps($table, $data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        $values = array_values($data);
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas (ex: order)
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [{$table}] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Helper para atualizar timestamps como strings antes de salvar (compatível com SQL Server)
     */
    private function prepareModelForSave($model)
    {
        // Garantir que updated_at seja string antes de salvar
        $model->updated_at = now()->format('Y-m-d H:i:s');
        
        // Se for um novo modelo, garantir created_at também
        if (!$model->exists) {
            $model->created_at = now()->format('Y-m-d H:i:s');
        }
        
        return $model;
    }

    /**
     * Helper para atualizar modelos com timestamps como strings (compatível com SQL Server)
     */
    private function updateModelWithStringTimestamps($model, array $data)
    {
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Usar DB::statement() para garantir que updated_at seja string
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CAST
            if ($column === 'updated_at' || $column === 'approved_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo com relacionamentos para ter os valores atualizados
        // Se for PurchaseQuoteApproval, carregar o relacionamento approver
        if ($model instanceof \App\Models\PurchaseQuoteApproval) {
            $model->refresh();
            $model->load('approver');
        } else {
            $model->refresh();
        }
        
        return $model;
    }

    protected function transitionStatus(PurchaseQuote $quote, PurchaseQuoteStatus $status, ?string $notes = null): void
    {
        // Usar helper para atualizar com timestamps como strings
        $this->updateModelWithStringTimestamps($quote, [
            'current_status_id' => $status->id,
            'current_status_slug' => $status->slug,
            'current_status_label' => $status->label,
            'updated_by' => auth()->id(),
        ]);

        // Usar helper para inserir com timestamps como strings
        $this->insertWithStringTimestamps('purchase_quote_status_histories', [
            'purchase_quote_id' => $quote->id,
            'status_id' => $status->id,
            'status_slug' => $status->slug,
            'status_label' => $status->label,
            'acted_by' => auth()->id(),
            'acted_by_name' => optional(auth()->user())->nome_completo ?? optional(auth()->user())->name,
            'notes' => $notes,
        ]);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10;

        $user = auth()->user();
        $approvalService = app(PurchaseQuoteApprovalService::class);
        
        // Obter company_id do header ou da primeira empresa do usuário
        $companyId = (int) $request->header('company-id');
        if (!$companyId && $user->companies()->exists()) {
            $companyId = $user->companies()->first()->id;
        }
        
        // Verificar se o usuário é apenas comprador (tem apenas nível COMPRADOR)
        $userLevels = [];
        if ($companyId) {
            $userLevels = $approvalService->getUserApprovalLevels($user, $companyId);
        }
        
        // Se não encontrou níveis com company_id, tentar sem company_id
        if (empty($userLevels)) {
            $userGroups = $user->groups()->pluck('name')->toArray();
            $groupToLevelMap = [
                'Comprador' => 'COMPRADOR',
                'COMPRADOR' => 'COMPRADOR',
                'Gerente Local' => 'GERENTE_LOCAL',
                'GERENTE LOCAL' => 'GERENTE_LOCAL',
                'Engenheiro' => 'ENGENHEIRO',
                'ENGENHEIRO' => 'ENGENHEIRO',
                'Gerente Geral' => 'GERENTE_GERAL',
                'GERENTE GERAL' => 'GERENTE_GERAL',
                'Diretor' => 'DIRETOR',
                'DIRETOR' => 'DIRETOR',
                'Presidente' => 'PRESIDENTE',
                'PRESIDENTE' => 'PRESIDENTE',
            ];
            
            foreach ($userGroups as $groupName) {
                foreach ($groupToLevelMap as $mapGroup => $level) {
                    if (stripos($groupName, $mapGroup) !== false) {
                        if (!in_array($level, $userLevels)) {
                            $userLevels[] = $level;
                        }
                    }
                }
            }
        }
        
        // Se o usuário é apenas comprador (tem apenas COMPRADOR e nenhum outro nível), não mostrar solicitações
        // a menos que seja explicitamente solicitado via my_quotes
        $isOnlyBuyer = count($userLevels) === 1 && in_array('COMPRADOR', $userLevels);
        
        if ($isOnlyBuyer && (!$request->filled('my_quotes') || $request->get('my_quotes') !== 'true')) {
            // Comprador não deve ver solicitações, retornar vazio
            return response()->json([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], Response::HTTP_OK);
        }

        $query = PurchaseQuote::query()->with(['status', 'items'])->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('current_status_slug', $request->get('status'));
        }

        // Filtro para mostrar apenas cotações do comprador logado
        if ($request->filled('my_quotes') && $request->get('my_quotes') === 'true') {
            $buyerId = auth()->id();
            $query->whereNotNull('buyer_id')->where('buyer_id', $buyerId);
        }
        
        // NOTA: Não aplicar filtro de company_id por padrão para mostrar todas as cotações
        // O filtro de company_id só é aplicado quando my_approvals está ativo

        // Filtro para mostrar apenas cotações pendentes no nível do usuário logado
        if ($request->filled('my_approvals') && $request->get('my_approvals') === 'true') {
            $user = auth()->user();
            $approvalService = app(PurchaseQuoteApprovalService::class);
            
            // Obter company_id do header ou da primeira empresa do usuário
            $companyId = (int) $request->header('company-id');
            if (!$companyId && $user->companies()->exists()) {
                $companyId = $user->companies()->first()->id;
            }
            
            // Obter níveis que o usuário pode aprovar (tentar com company_id, se não encontrar, tentar sem)
            $userLevels = [];
            if ($companyId) {
                $userLevels = $approvalService->getUserApprovalLevels($user, $companyId);
            }
            
            // Se não encontrou níveis com company_id, tentar sem company_id (para cotações sem company_id)
            if (empty($userLevels)) {
                // Tentar buscar grupos sem filtrar por company_id
                $userGroups = $user->groups()->pluck('name')->toArray();
                
                $groupToLevelMap = [
                    'Comprador' => 'COMPRADOR',
                    'COMPRADOR' => 'COMPRADOR',
                    'Gerente Local' => 'GERENTE_LOCAL',
                    'GERENTE LOCAL' => 'GERENTE_LOCAL',
                    'Engenheiro' => 'ENGENHEIRO',
                    'ENGENHEIRO' => 'ENGENHEIRO',
                    'Gerente Geral' => 'GERENTE_GERAL',
                    'GERENTE GERAL' => 'GERENTE_GERAL',
                    'Diretor' => 'DIRETOR',
                    'DIRETOR' => 'DIRETOR',
                    'Presidente' => 'PRESIDENTE',
                    'PRESIDENTE' => 'PRESIDENTE',
                ];
                
                foreach ($userGroups as $groupName) {
                    foreach ($groupToLevelMap as $mapGroup => $level) {
                        if (stripos($groupName, $mapGroup) !== false) {
                            if (!in_array($level, $userLevels)) {
                                $userLevels[] = $level;
                            }
                        }
                    }
                }
            }
            
            if (!empty($userLevels)) {
                // Separar lógica por tipo de usuário
                $isDirector = in_array('DIRETOR', $userLevels);
                $intermediateLevels = ['ENGENHEIRO', 'GERENTE_LOCAL', 'GERENTE_GERAL'];
                $isIntermediate = !empty(array_intersect($userLevels, $intermediateLevels));
                
                // Se é DIRETOR: mostrar APENAS cotações com status "analise_gerencia"
                if ($isDirector) {
                    $query->where('current_status_slug', 'analise_gerencia')
                        ->whereHas('approvals', function ($approvalQ) {
                            // Verificar se DIRETOR tem aprovação pendente
                            $approvalQ->where('approval_level', 'DIRETOR')
                                ->where('required', true)
                                ->where('approved', false);
                        })
                        // Garantir que TODAS as assinaturas anteriores (exceto DIRETOR) foram aprovadas
                        ->whereDoesntHave('approvals', function ($approvalQ) {
                            $approvalQ->where('approval_level', '!=', 'DIRETOR')
                                ->where('required', true)
                                ->where('approved', false);
                        });
                }
                // Se é ENGENHEIRO, GERENTE_LOCAL ou GERENTE_GERAL (e não é DIRETOR): mostrar APENAS cotações com status "finalizada" ou "analisada"
                elseif ($isIntermediate) {
                    $query->whereIn('current_status_slug', ['finalizada', 'analisada', 'analisada_aguardando'])
                        ->whereHas('approvals', function ($approvalQ) use ($userLevels, $intermediateLevels) {
                            // Verificar se tem aprovação pendente para algum dos níveis intermediários do usuário
                            $approvalQ->whereIn('approval_level', array_intersect($userLevels, $intermediateLevels))
                                ->where('required', true)
                                ->where('approved', false);
                        });
                }
                // Se não é DIRETOR nem intermediário, seguir lógica normal de hierarquia
                else {
                    $query->whereHas('approvals', function ($approvalQ) use ($userLevels) {
                        $approvalQ->whereIn('approval_level', $userLevels)
                            ->where('required', true)
                            ->where('approved', false);
                    });
                }
                
                if ($companyId) {
                    // Filtrar por company_id se disponível, mas também incluir cotações sem company_id
                    $query->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                          ->orWhereNull('company_id');
                    });
                }
            } else {
                // Se o usuário não tem níveis de aprovação, retornar vazio
                $query->whereRaw('1 = 0');
            }
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $term = '%' . Str::upper($search) . '%';
                $q->whereRaw('UPPER(quote_number) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(requester_name) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(company_name) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(main_cost_center_description) LIKE ?', [$term])
                    ->orWhereRaw('UPPER(work_front) LIKE ?', [$term]);
            });
        }

        $quotes = $query->withCount('orders')->paginate($perPage);

        $quotes->getCollection()->transform(function (PurchaseQuote $quote) {
            $firstItem = $quote->items->first();
            $solicitacaoLabel = $quote->quote_number;
            if ($firstItem && $firstItem->description) {
                $solicitacaoLabel .= ' – ' . $firstItem->description;
            }

            $valorTotal = 0;

            $itemsData = $quote->items->map(function (PurchaseQuoteItem $item) use ($quote, &$valorTotal) {
                $selectedTotal = $item->selected_total_cost;

                if ($selectedTotal === null && $item->selected_unit_cost !== null) {
                    $selectedTotal = $item->selected_unit_cost * ($item->quantity ?? 0);
                }

                if ($selectedTotal === null) {
                    $supplierItem = null;

                    if ($item->selectedSupplier && $item->selectedSupplier->items) {
                        $supplierItem = $item->selectedSupplier->items->firstWhere('purchase_quote_item_id', $item->id);
                    }

                    if (!$supplierItem) {
                        $supplierItem = $quote->suppliers
                            ->map(fn ($supplier) => $supplier->items->firstWhere('purchase_quote_item_id', $item->id))
                            ->filter()
                            ->sortBy(function ($supplierItem) {
                                if ($supplierItem?->final_cost !== null) {
                                    return $supplierItem->final_cost;
                                }

                                return $supplierItem?->unit_cost ?? PHP_FLOAT_MAX;
                            })
                            ->first();
                    }

                    if ($supplierItem) {
                        if ($supplierItem->final_cost !== null) {
                            $selectedTotal = $supplierItem->final_cost;
                        } elseif ($supplierItem->unit_cost !== null) {
                            $selectedTotal = $supplierItem->unit_cost * ($item->quantity ?? 0);
                        }
                    }
                }

                if ($selectedTotal === null) {
                    $selectedTotal = 0;
                }

                $valorTotal += $selectedTotal;

                return [
                    'id' => $item->id,
                    'product_code' => $item->product_code,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'cost_center_code' => $item->cost_center_code,
                    'cost_center_description' => $item->cost_center_description,
                    'selected_total' => $selectedTotal,
                ];
            });

            return [
                'id' => $quote->id,
                'numero' => $quote->quote_number,
                'data' => optional($quote->requested_at)->format('d/m/Y'),
                'solicitante' => $quote->requester_name,
                'empresa' => $quote->company_name,
                'centro_custo' => $quote->main_cost_center_description ?? $quote->main_cost_center_code,
                'frente_obra' => $quote->work_front,
                'solicitacao' => $solicitacaoLabel,
                'status' => [
                    'slug' => $quote->current_status_slug,
                    'label' => $quote->current_status_label,
                    'perfil' => optional($quote->status)->required_profile,
                ],
                'buyer' => [
                    'id' => $quote->buyer_id ? (int) $quote->buyer_id : null,
                    'name' => $quote->buyer_name,
                ],
                'valor_total' => $valorTotal,
                'itens' => $itemsData,
                'orders_count' => $quote->orders_count ?? 0,
            ];
        });

        return response()->json([
            'data' => $quotes->items(),
            'pagination' => [
                'current_page' => $quotes->currentPage(),
                'per_page' => $quotes->perPage(),
                'total' => $quotes->total(),
                'last_page' => $quotes->lastPage(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Request $request, PurchaseQuote $quote)
    {
        $quote->load([
            'items.selectedSupplier',
            'items.selectedSupplier.items',
            'suppliers.items',
            'messages.user',
            'approvals.approver',
            'statusHistory' => function ($query) {
                $query->orderByDesc('acted_at');
            },
            'statusHistory.status',
            'company',
        ]);

        $itemsCollection = $quote->items;

        $cotacoes = $quote->suppliers->map(function (PurchaseQuoteSupplier $supplier) use ($itemsCollection) {
            $itemQuotes = $itemsCollection->map(function (PurchaseQuoteItem $item) use ($supplier) {
                $supplierItem = $supplier->items->firstWhere('purchase_quote_item_id', $item->id);

                return [
                    'id' => $supplierItem?->id,
                    'item_id' => $item->id,
                    'marca' => $supplierItem?->brand,
                    'custo_unit' => $supplierItem?->unit_cost,
                    'ipi' => $supplierItem?->ipi,
                    'custo_ipi' => $supplierItem?->unit_cost_with_ipi,
                    'difal' => $supplierItem?->difal,
                    'icms' => $supplierItem?->icms,
                    'icms_total' => $supplierItem?->icms_total,
                    'custo_final' => $supplierItem?->final_cost,
                ];
            });

            $municipality = $supplier->municipality;
            $state = $supplier->state;
            $municipioEstado = null;
            if ($municipality || $state) {
                $parts = array_filter([trim($municipality ?? ''), trim($state ?? '')]);
                $municipioEstado = !empty($parts) ? implode(' - ', $parts) : null;
            }

            return [
                'id' => $supplier->id,
                'codigo' => $supplier->supplier_code,
                'nome' => $supplier->supplier_name,
                'cnpj' => $supplier->supplier_document,
                'municipio' => $municipality,
                'estado' => $state,
                'municipio_estado' => $municipioEstado,
                'vendedor' => $supplier->vendor_name,
                'telefone' => $supplier->vendor_phone,
                'email' => $supplier->vendor_email,
                'proposta' => $supplier->proposal_number,
                'condicao_pagamento' => $supplier->payment_condition_code ? [
                    'codigo' => $supplier->payment_condition_code,
                    'descricao' => $supplier->payment_condition_description,
                ] : null,
                'tipo_frete' => $supplier->freight_type,
                'valor_frete' => $supplier->freight_value,
                'desconto' => $supplier->discount,
                'difal_percent' => $supplier->difal_percent,
                'itens' => $itemQuotes,
            ];
        });

        $selecoes = $itemsCollection->map(function (PurchaseQuoteItem $item) {
            if (!$item->selected_supplier_id && !$item->selected_unit_cost && !$item->selected_total_cost && empty($item->selection_reason)) {
                return null;
            }

            return [
                'item_id' => $item->id,
                'supplier_id' => $item->selected_supplier_id,
                'supplier_codigo' => optional($item->selectedSupplier)->supplier_code,
                'valor_unitario' => $item->selected_unit_cost,
                'valor_total' => $item->selected_total_cost,
                'motivo' => $item->selection_reason,
            ];
        })->filter()->values();

        $buyersQuery = User::query()
            ->select(['id', 'nome_completo', 'login'])
            ->whereHas('groups', function ($query) use ($quote) {
                if ($quote->company_id) {
                    $query->where('company_id', $quote->company_id);
                }

                $query->where(function ($groupQuery) {
                    $groupQuery->where('name', 'LIKE', '%comprador%')
                        ->orWhereHas('items', function ($itemQuery) {
                            $itemQuery->where('slug', 'LIKE', '%comprador%');
                        });
                });
            })
            ->orderBy('nome_completo')
            ->distinct();

        $buyers = $buyersQuery->get()->map(fn (User $user) => [
            'id' => $user->id,
            'label' => $user->nome_completo ?? $user->login,
        ]);

        // Buscar razão social da empresa
        $empresaNome = $quote->company_name;
        
        // Tentar buscar a empresa de diferentes formas
        $company = null;
        $companyId = $quote->company_id;
        
        // Se não tem company_id na cotação, tentar pegar do header da requisição
        if (!$companyId) {
            $companyId = $request->header('company-id');
        }
        
        if ($companyId) {
            // Primeiro tenta pelo relacionamento
            if ($quote->relationLoaded('company') && $quote->company && $quote->company->id == $companyId) {
                $company = $quote->company;
            } else {
                // Se não estiver carregado ou não corresponde, tenta carregar
                try {
                    if ($quote->company_id == $companyId) {
                        $quote->load('company');
                        if ($quote->company) {
                            $company = $quote->company;
                        }
                    } else {
                        // Buscar diretamente se o ID não corresponder
                        $company = \App\Models\Company::find($companyId);
                    }
                } catch (\Exception $e) {
                    // Se falhar, tenta buscar diretamente
                    try {
                        $company = \App\Models\Company::find($companyId);
                    } catch (\Exception $e2) {
                        // Ignora erro
                    }
                }
            }
        }
        
        // Se encontrou a empresa, usar razão social ou nome
        if ($company) {
            if (!empty($company->razao_social)) {
                $empresaNome = $company->razao_social;
            } elseif (!empty($company->company)) {
                $empresaNome = $company->company;
            }
            // Se ainda não tem nada, mantém o company_name que já estava
        }
        
        // Se ainda está vazio e tem company_name na cotação, usar ele
        if (empty($empresaNome) && !empty($quote->company_name)) {
            $empresaNome = $quote->company_name;
        }

        return response()->json([
            'data' => [
                'id' => $quote->id,
                'numero' => $quote->quote_number,
                'data' => optional($quote->requested_at)->format('d/m/Y'),
                'solicitante' => $quote->requester_name,
                'empresa' => $empresaNome,
                'company_id' => $quote->company_id,
                'local' => $quote->location,
                'observacao' => $quote->observation,
                'requires_response' => (bool) $quote->requires_response,
                'centro_custo' => [
                    'codigo' => $quote->main_cost_center_code,
                    'descricao' => $quote->main_cost_center_description,
                ],
                'status' => [
                    'slug' => $quote->current_status_slug,
                    'label' => $quote->current_status_label,
                ],
                'buyer' => [
                    'id' => $quote->buyer_id ? (int) $quote->buyer_id : null,
                    'name' => $quote->buyer_name,
                ],
                'mensagens' => $quote->messages
                    ->sortBy('created_at')
                    ->map(fn (PurchaseQuoteMessage $message) => [
                        'id' => $message->id,
                        'autor' => optional($message->user)->nome_completo ?? optional($message->user)->name ?? 'Sistema',
                        'tipo' => $message->type,
                        'mensagem' => $message->message,
                        'data' => optional($message->created_at)->format('d/m/Y H:i'),
                        'data_iso' => optional($message->created_at)->toIso8601String(),
                    ])
                    ->values(),
                'cotacoes' => $cotacoes,
                'selecoes' => $selecoes,
                'itens' => $quote->items->map(function (PurchaseQuoteItem $item) use ($quote) {
                    // Buscar referência do produto do estoque se não houver no item
                    $referencia = $item->reference;
                    if (empty($referencia) && !empty($item->product_code) && !empty($quote->company_id)) {
                        $produto = \App\Models\StockProduct::where('code', $item->product_code)
                            ->where('company_id', $quote->company_id)
                            ->first();
                        $referencia = $produto->reference ?? null;
                    }
                    
                    return [
                        'id' => $item->id,
                        'numero' => $item->id,
                        'codigo' => $item->product_code,
                        'referencia' => $referencia,
                        'mercadoria' => $item->description,
                        'quantidade' => $item->quantity,
                        'unidade' => $item->unit,
                        'aplicacao' => $item->application,
                        'prioridade' => $item->priority_days,
                        'tag' => $item->tag,
                        'centro_custo' => ($item->cost_center_code || $item->cost_center_description) ? [
                            'codigo' => $item->cost_center_code,
                            'descricao' => $item->cost_center_description,
                            'classe' => null, // O campo classe não está armazenado no banco, mas mantemos para compatibilidade
                        ] : null,
                    ];
                }),
                'historico' => $quote->statusHistory->map(fn (PurchaseQuoteStatusHistory $history) => [
                    'status' => $history->status_label,
                    'perfil' => optional($history->status)->required_profile,
                    'usuario' => $history->acted_by_name,
                    'observacao' => $history->notes,
                    'data' => optional($history->acted_at)->format('d/m/Y H:i'),
                ]),
                'aprovacoes' => $quote->approvals->map(fn ($approval) => [
                    'level' => $approval->approval_level,
                    'required' => $approval->required,
                    'approved' => $approval->approved,
                    'approved_by' => $approval->approved_by_name,
                    'approved_at' => $approval->approved_at ? $approval->approved_at->format('d/m/Y H:i') : null,
                    'order' => $approval->order,
                    'notes' => $approval->notes,
                ])->sortBy('order')->values(),
                // Informações de permissão do usuário atual
                'permissions' => [
                    'can_edit' => $this->canUserEditQuote($quote, auth()->user()),
                    'can_approve' => $this->canUserApproveQuote($quote, auth()->user()),
                    'next_pending_level' => $this->getNextPendingLevelForCurrentUser($quote, auth()->user()),
                ],
            ],
            'buyers' => $buyers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero' => 'nullable|string|max:30|unique:purchase_quotes,quote_number',
            'data_solicitacao' => 'nullable|date',
            'solicitante.id' => 'nullable|integer|exists:users,id',
            'solicitante.label' => 'nullable|string|max:191',
            'empresa.id' => 'nullable|integer|exists:companies,id',
            'empresa.label' => 'nullable|string|max:191',
            'local' => 'nullable|string|max:191',
            'work_front' => 'nullable|string|max:191',
            'observacao' => 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.codigo' => 'nullable|string|max:100',
            'itens.*.referencia' => 'nullable|string|max:100',
            'itens.*.mercadoria' => 'required|string|max:255',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
            'itens.*.unidade' => 'nullable|string|max:20',
            'itens.*.aplicacao' => 'nullable|string',
            'itens.*.prioridade' => 'nullable|integer|min:0',
            'itens.*.tag' => 'nullable|string|max:100',
            'itens.*.centro_custo.codigo' => 'nullable|string|max:50',
            'itens.*.centro_custo.descricao' => 'nullable|string',
            'itens.*.centro_custo.classe' => 'nullable|string|max:20',
        ]);

        // Verificar se o usuário tem permissão para criar cotações
        $user = auth()->user();
        if (!$user || !$user->hasPermission('create_cotacoes')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar cotações.',
            ], Response::HTTP_FORBIDDEN);
        }

        $status = PurchaseQuoteStatus::where('slug', 'aguardando')->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status padrão não encontrado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $quoteNumber = $validated['numero'] ?? PurchaseQuote::generateNextNumber();

        // Garantir que requested_at seja uma string no formato Y-m-d (não Carbon)
        // SQL Server precisa receber como string literal
        $requestedAt = null;
        if (!empty($validated['data_solicitacao'])) {
            try {
                // Se vier no formato brasileiro (dd/mm/yyyy), converter
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $validated['data_solicitacao'])) {
                    $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['data_solicitacao']);
                    $requestedAt = $parsed->format('Y-m-d'); // String, não Carbon
                } else {
                    // Se já vier no formato ISO (Y-m-d), validar e usar
                    $parsed = \Carbon\Carbon::parse($validated['data_solicitacao']);
                    $requestedAt = $parsed->format('Y-m-d'); // String, não Carbon
                }
            } catch (\Exception $e) {
                $requestedAt = now()->format('Y-m-d'); // String, não Carbon
            }
        } else {
            $requestedAt = now()->format('Y-m-d'); // String, não Carbon
        }

        // Variável para armazenar SQL de debug (acessível no catch)
        $debugSql = null;
        
        DB::beginTransaction();

        try {
            // Habilitar log de queries para debug
            DB::enableQueryLog();
            
            // Garantir que todas as datas sejam strings
            $createdAt = now()->format('Y-m-d H:i:s');
            $updatedAt = now()->format('Y-m-d H:i:s');
            
            // Usar DB::statement() com bindings seguros para garantir strings literais
            $insertData = [
                'quote_number' => $quoteNumber,
                'requester_id' => data_get($validated, 'solicitante.id'),
                'requester_name' => data_get($validated, 'solicitante.label'),
                'company_id' => data_get($validated, 'empresa.id'),
                'company_name' => data_get($validated, 'empresa.label'),
                'location' => $validated['local'] ?? null,
                'work_front' => $validated['work_front'] ?? null,
                'observation' => $validated['observacao'] ?? null,
                'current_status_id' => $status->id,
                'current_status_slug' => $status->slug,
                'current_status_label' => $status->label,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];
            
            // Construir SQL com CAST para forçar tipos corretos no SQL Server
            $columns = array_keys($insertData);
            $placeholders = array_fill(0, count($insertData), '?');
            $values = array_values($insertData);
            
            // Adicionar campos de data com CAST
            $columns[] = 'requested_at';
            $placeholders[] = "CAST(? AS DATE)";
            $values[] = $requestedAt;
            
            $columns[] = 'created_at';
            $placeholders[] = "CAST(? AS DATETIME2)";
            $values[] = $createdAt;
            
            $columns[] = 'updated_at';
            $placeholders[] = "CAST(? AS DATETIME2)";
            $values[] = $updatedAt;
            
            // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
            $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
            
            $sql = "INSERT INTO [purchase_quotes] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            DB::statement($sql, $values);
            
            // Buscar o ID do último registro inserido
            $quoteId = DB::getPdo()->lastInsertId();
            
            // Carregar o modelo usando o ID retornado
            $quote = PurchaseQuote::findOrFail($quoteId);
            
            // Capturar o SQL executado para debug (será usado apenas se houver erro)
            $queries = DB::getQueryLog();
            $lastQuery = end($queries);
            
            if ($lastQuery) {
                $sql = $lastQuery['query'];
                $bindings = $lastQuery['bindings'];
                
                // Substituir placeholders pelos valores reais
                foreach ($bindings as $binding) {
                    $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                    $sql = preg_replace('/\?/', $value, $sql, 1);
                }
                
                $debugSql = $sql;
                
                Log::info('=== SQL EXECUTADO (para testar diretamente no banco) ===');
                Log::info($sql);
                Log::info('=== FIM DO SQL ===');
            }

            $mainCostCenterCode = null;
            $mainCostCenterDescription = null;

            // Garantir que todas as datas sejam strings
            $itemCreatedAt = now()->format('Y-m-d H:i:s');
            $itemUpdatedAt = now()->format('Y-m-d H:i:s');

            foreach ($validated['itens'] as $item) {
                $centro = $item['centro_custo'] ?? null;
                $costCenterCode = $centro['codigo'] ?? null;
                $costCenterDescription = $centro['descricao'] ?? null;

                // Usar DB::table() diretamente para garantir que valores sejam strings literais
                $itemData = [
                    'purchase_quote_id' => $quote->id,
                    'product_code' => $item['codigo'] ?? null,
                    'reference' => $item['referencia'] ?? null,
                    'description' => $item['mercadoria'],
                    'quantity' => $item['quantidade'] ?? 0,
                    'unit' => $item['unidade'] ?? null,
                    'application' => $item['aplicacao'] ?? null,
                    'priority_days' => $item['prioridade'] ?? null,
                    'tag' => $item['tag'] ?? null,
                    'cost_center_code' => $costCenterCode,
                    'cost_center_description' => $costCenterDescription,
                ];
                
                // Construir SQL com CAST para forçar tipos corretos no SQL Server
                $columns = array_keys($itemData);
                $placeholders = array_fill(0, count($itemData), '?');
                $values = array_values($itemData);
                
                // Adicionar campos de data com CAST
                $columns[] = 'created_at';
                $placeholders[] = "CAST(? AS DATETIME2)";
                $values[] = $itemCreatedAt;
                
                $columns[] = 'updated_at';
                $placeholders[] = "CAST(? AS DATETIME2)";
                $values[] = $itemUpdatedAt;
                
                // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
                $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
                
                $sql = "INSERT INTO [purchase_quote_items] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
                
                DB::statement($sql, $values);
                
                // Buscar o ID do último registro inserido
                $itemId = DB::getPdo()->lastInsertId();
                
                // Carregar o modelo usando o ID retornado (opcional, se precisar usar depois)
                $createdItem = PurchaseQuoteItem::findOrFail($itemId);

                if (!$mainCostCenterCode && $costCenterCode) {
                    $mainCostCenterCode = $costCenterCode;
                    $mainCostCenterDescription = $costCenterDescription;
                }
            }

            if ($mainCostCenterCode) {
                $this->updateModelWithStringTimestamps($quote, [
                    'main_cost_center_code' => $mainCostCenterCode,
                    'main_cost_center_description' => $mainCostCenterDescription,
                ]);
            }

            // Usar helper para inserir com timestamps como strings
            $this->insertWithStringTimestamps('purchase_quote_status_histories', [
                'purchase_quote_id' => $quote->id,
                'status_id' => $status->id,
                'status_slug' => $status->slug,
                'status_label' => $status->label,
                'acted_by' => auth()->id(),
                'acted_by_name' => optional(auth()->user())->nome_completo ?? optional(auth()->user())->name,
                'notes' => 'Cotação criada e aguardando autorização.',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cotação criada com sucesso.',
                'data' => [
                    'id' => $quote->id,
                    'numero' => $quote->quote_number,
                ],
            ], Response::HTTP_CREATED);
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Falha ao criar cotação', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Adicionar SQL de debug na resposta de erro
            $errorMessage = $exception->getMessage();
            if ($debugSql) {
                $errorMessage .= "\n\n=== SQL PARA TESTAR DIRETAMENTE NO BANCO ===\n" . $debugSql;
            }

            // Adicionar SQL de debug na resposta de erro
            $errorMessage = $exception->getMessage();
            if ($debugSql) {
                $errorMessage .= "\n\n=== SQL PARA TESTAR DIRETAMENTE NO BANCO ===\n" . $debugSql;
            }

            return response()->json([
                'message' => 'Não foi possível salvar a cotação.',
                'error' => $errorMessage,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, PurchaseQuote $quote)
    {
        // Verificar se o usuário pode editar esta cotação
        if (!$this->canUserEditQuote($quote, auth()->user())) {
            return response()->json([
                'message' => 'Você não tem permissão para editar esta solicitação.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Se a solicitação estiver reprovada, voltar para "aguardando" ao salvar
        $statusNovo = null;
        if ($quote->current_status_slug === 'reprovado') {
            $statusAguardando = PurchaseQuoteStatus::where('slug', 'aguardando')->first();
            if (!$statusAguardando) {
                return response()->json([
                    'message' => 'Status "aguardando" não configurado. Execute as migrations novamente.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $statusNovo = $statusAguardando;
        }

        $validated = $request->validate([
            'numero' => 'nullable|string|max:30|unique:purchase_quotes,quote_number,' . $quote->id,
            'data_solicitacao' => 'nullable|date',
            'solicitante.id' => 'nullable|integer|exists:users,id',
            'solicitante.label' => 'nullable|string|max:191',
            'empresa.id' => 'nullable|integer|exists:companies,id',
            'empresa.label' => 'nullable|string|max:191',
            'local' => 'nullable|string|max:191',
            'work_front' => 'nullable|string|max:191',
            'observacao' => 'nullable|string',
            'itens' => 'required|array|min:1',
            'itens.*.id' => 'nullable|integer|exists:purchase_quote_items,id',
            'itens.*.codigo' => 'nullable|string|max:100',
            'itens.*.referencia' => 'nullable|string|max:100',
            'itens.*.mercadoria' => 'required|string|max:255',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
            'itens.*.unidade' => 'nullable|string|max:20',
            'itens.*.aplicacao' => 'nullable|string',
            'itens.*.prioridade' => 'nullable|integer|min:0',
            'itens.*.tag' => 'nullable|string|max:100',
            'itens.*.centro_custo.codigo' => 'nullable|string|max:50',
            'itens.*.centro_custo.descricao' => 'nullable|string',
            'itens.*.centro_custo.classe' => 'nullable|string|max:20',
        ]);

        // Garantir que requested_at seja uma string no formato Y-m-d
        $requestedAt = null;
        if (!empty($validated['data_solicitacao'])) {
            try {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $validated['data_solicitacao'])) {
                    $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['data_solicitacao']);
                    $requestedAt = $parsed->format('Y-m-d');
                } else {
                    $parsed = \Carbon\Carbon::parse($validated['data_solicitacao']);
                    $requestedAt = $parsed->format('Y-m-d');
                }
            } catch (\Exception $e) {
                $requestedAt = $quote->requested_at ? (\Carbon\Carbon::parse($quote->requested_at)->format('Y-m-d')) : now()->format('Y-m-d');
            }
        } else {
            $requestedAt = $quote->requested_at ? (\Carbon\Carbon::parse($quote->requested_at)->format('Y-m-d')) : now()->format('Y-m-d');
        }

        DB::beginTransaction();

        try {
            // Atualizar dados principais da cotação
            $updateData = [
                'requester_id' => data_get($validated, 'solicitante.id'),
                'requester_name' => data_get($validated, 'solicitante.label'),
                'company_id' => data_get($validated, 'empresa.id'),
                'company_name' => data_get($validated, 'empresa.label'),
                'location' => $validated['local'] ?? null,
                'work_front' => $validated['work_front'] ?? null,
                'observation' => $validated['observacao'] ?? null,
                'updated_by' => auth()->id(),
            ];

            if (!empty($validated['numero'])) {
                $updateData['quote_number'] = $validated['numero'];
            }

            $this->updateModelWithStringTimestamps($quote, $updateData);

            // Atualizar requested_at separadamente
            if ($requestedAt) {
                DB::statement("UPDATE [purchase_quotes] SET [requested_at] = CAST(? AS DATE) WHERE [id] = ?", [$requestedAt, $quote->id]);
                $quote->refresh();
            }

            // Atualizar itens: remover os que não estão mais na lista
            $itemIdsFromRequest = array_filter(array_map(fn($item) => $item['id'] ?? null, $validated['itens']));
            $quote->items()->whereNotIn('id', $itemIdsFromRequest)->delete();

            $mainCostCenterCode = null;
            $mainCostCenterDescription = null;
            $itemCreatedAt = now()->format('Y-m-d H:i:s');
            $itemUpdatedAt = now()->format('Y-m-d H:i:s');

            // Atualizar ou criar itens
            foreach ($validated['itens'] as $itemData) {
                $centro = $itemData['centro_custo'] ?? null;
                $costCenterCode = $centro['codigo'] ?? null;
                $costCenterDescription = $centro['descricao'] ?? null;

                if (!empty($itemData['id'])) {
                    // Atualizar item existente
                    $item = $quote->items()->find($itemData['id']);
                    if ($item) {
                        $itemUpdateData = [
                            'product_code' => $itemData['codigo'] ?? null,
                            'reference' => $itemData['referencia'] ?? null,
                            'description' => $itemData['mercadoria'],
                            'quantity' => $itemData['quantidade'] ?? 0,
                            'unit' => $itemData['unidade'] ?? null,
                            'application' => $itemData['aplicacao'] ?? null,
                            'priority_days' => $itemData['prioridade'] ?? null,
                            'tag' => $itemData['tag'] ?? null,
                            'cost_center_code' => $costCenterCode,
                            'cost_center_description' => $costCenterDescription,
                        ];

                        $this->updateModelWithStringTimestamps($item, $itemUpdateData);
                    }
                } else {
                    // Criar novo item
                    $newItemData = [
                        'purchase_quote_id' => $quote->id,
                        'product_code' => $itemData['codigo'] ?? null,
                        'reference' => $itemData['referencia'] ?? null,
                        'description' => $itemData['mercadoria'],
                        'quantity' => $itemData['quantidade'] ?? 0,
                        'unit' => $itemData['unidade'] ?? null,
                        'application' => $itemData['aplicacao'] ?? null,
                        'priority_days' => $itemData['prioridade'] ?? null,
                        'tag' => $itemData['tag'] ?? null,
                        'cost_center_code' => $costCenterCode,
                        'cost_center_description' => $costCenterDescription,
                    ];

                    $this->insertWithStringTimestamps('purchase_quote_items', $newItemData);
                }

                if (!$mainCostCenterCode && $costCenterCode) {
                    $mainCostCenterCode = $costCenterCode;
                    $mainCostCenterDescription = $costCenterDescription;
                }
            }

            // Atualizar centro de custo principal
            if ($mainCostCenterCode) {
                $this->updateModelWithStringTimestamps($quote, [
                    'main_cost_center_code' => $mainCostCenterCode,
                    'main_cost_center_description' => $mainCostCenterDescription,
                ]);
            }

            // Se estava reprovada, mudar para "aguardando"
            if ($statusNovo) {
                $this->transitionStatus($quote, $statusNovo, 'Solicitação editada e retornada para aguardando autorização.');
            }

            DB::commit();

            $quote->refresh();
            $quote->load(['items', 'status']);

            return response()->json([
                'message' => 'Solicitação atualizada com sucesso.',
                'data' => [
                    'id' => $quote->id,
                    'numero' => $quote->quote_number,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Falha ao atualizar solicitação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível atualizar a solicitação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Aplica edições na cotação (usado durante aprovação quando tem permissão de editar)
     */
    private function applyQuoteEditions(PurchaseQuote $quote, array $validated): void
    {
        // Atualizar dados principais da cotação se fornecidos
        $updateData = [];
        
        if (isset($validated['solicitante'])) {
            $updateData['requester_id'] = data_get($validated, 'solicitante.id');
            $updateData['requester_name'] = data_get($validated, 'solicitante.label');
        }
        
        if (isset($validated['empresa'])) {
            $updateData['company_id'] = data_get($validated, 'empresa.id');
            $updateData['company_name'] = data_get($validated, 'empresa.label');
        }
        
        if (isset($validated['local'])) {
            $updateData['location'] = $validated['local'];
        }
        
        if (isset($validated['work_front'])) {
            $updateData['work_front'] = $validated['work_front'];
        }
        
        if (isset($validated['observacao'])) {
            $updateData['observation'] = $validated['observacao'];
        }
        
        if (!empty($validated['numero'])) {
            $updateData['quote_number'] = $validated['numero'];
        }
        
        if (!empty($updateData)) {
            $updateData['updated_by'] = auth()->id();
            $this->updateModelWithStringTimestamps($quote, $updateData);
        }
        
        // Atualizar requested_at se fornecido
        if (!empty($validated['data_solicitacao'])) {
            try {
                $requestedAt = null;
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $validated['data_solicitacao'])) {
                    $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['data_solicitacao']);
                    $requestedAt = $parsed->format('Y-m-d');
                } else {
                    $parsed = \Carbon\Carbon::parse($validated['data_solicitacao']);
                    $requestedAt = $parsed->format('Y-m-d');
                }
                
                if ($requestedAt) {
                    DB::statement("UPDATE [purchase_quotes] SET [requested_at] = CAST(? AS DATE) WHERE [id] = ?", [$requestedAt, $quote->id]);
                }
            } catch (\Exception $e) {
                // Ignorar erro de parsing de data
            }
        }
        
        // Atualizar itens se fornecidos
        if (!empty($validated['itens']) && is_array($validated['itens'])) {
            // Remover itens que não estão mais na lista
            $itemIdsFromRequest = array_filter(array_map(fn($item) => $item['id'] ?? null, $validated['itens']));
            if (!empty($itemIdsFromRequest)) {
                $quote->items()->whereNotIn('id', $itemIdsFromRequest)->delete();
            } else {
                // Se nenhum item tem ID, remover todos os existentes (serão recriados)
                $quote->items()->delete();
            }
            
            $mainCostCenterCode = null;
            $mainCostCenterDescription = null;
            $itemCreatedAt = now()->format('Y-m-d H:i:s');
            $itemUpdatedAt = now()->format('Y-m-d H:i:s');
            
            // Atualizar ou criar itens
            foreach ($validated['itens'] as $itemData) {
                $centro = $itemData['centro_custo'] ?? null;
                $costCenterCode = $centro['codigo'] ?? null;
                $costCenterDescription = $centro['descricao'] ?? null;
                
                if (!empty($itemData['id'])) {
                    // Atualizar item existente
                    $item = $quote->items()->find($itemData['id']);
                    if ($item) {
                        $itemUpdateData = [
                            'product_code' => $itemData['codigo'] ?? null,
                            'reference' => $itemData['referencia'] ?? null,
                            'description' => $itemData['mercadoria'] ?? $item->description,
                            'quantity' => $itemData['quantidade'] ?? $item->quantity,
                            'unit' => $itemData['unidade'] ?? null,
                            'application' => $itemData['aplicacao'] ?? null,
                            'priority_days' => $itemData['prioridade'] ?? null,
                            'tag' => $itemData['tag'] ?? null,
                            'cost_center_code' => $costCenterCode,
                            'cost_center_description' => $costCenterDescription,
                        ];
                        
                        $this->updateModelWithStringTimestamps($item, $itemUpdateData);
                    }
                } else {
                    // Criar novo item
                    $newItemData = [
                        'purchase_quote_id' => $quote->id,
                        'product_code' => $itemData['codigo'] ?? null,
                        'reference' => $itemData['referencia'] ?? null,
                        'description' => $itemData['mercadoria'] ?? '',
                        'quantity' => $itemData['quantidade'] ?? 0,
                        'unit' => $itemData['unidade'] ?? null,
                        'application' => $itemData['aplicacao'] ?? null,
                        'priority_days' => $itemData['prioridade'] ?? null,
                        'tag' => $itemData['tag'] ?? null,
                        'cost_center_code' => $costCenterCode,
                        'cost_center_description' => $costCenterDescription,
                    ];
                    
                    $this->insertWithStringTimestamps('purchase_quote_items', $newItemData);
                }
                
                if (!$mainCostCenterCode && $costCenterCode) {
                    $mainCostCenterCode = $costCenterCode;
                    $mainCostCenterDescription = $costCenterDescription;
                }
            }
            
            // Atualizar centro de custo principal se houver
            if ($mainCostCenterCode) {
                $this->updateModelWithStringTimestamps($quote, [
                    'main_cost_center_code' => $mainCostCenterCode,
                    'main_cost_center_description' => $mainCostCenterDescription,
                ]);
            }
        }
    }

    public function saveDetails(Request $request, PurchaseQuote $quote, PurchaseQuoteProductDefaultsService $productDefaultsService)
    {
        // Verificar se o usuário tem permissão para editar detalhes de cotação
        $user = auth()->user();
        if (!$user || !$user->hasPermission('edit_cotacoes_detalhes')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar detalhes de cotação.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'fornecedores' => 'required|array|min:1',
            'fornecedores.*.id' => 'nullable|integer|exists:purchase_quote_suppliers,id',
            'fornecedores.*.codigo' => 'nullable|string|max:60',
            'fornecedores.*.nome' => 'required|string|max:255',
            'fornecedores.*.cnpj' => 'nullable|string|max:30',
            'fornecedores.*.municipality' => 'nullable|string|max:100',
            'fornecedores.*.state' => 'nullable|string|max:2',
            'fornecedores.*.vendedor' => 'nullable|string|max:191',
            'fornecedores.*.telefone' => 'nullable|string|max:50',
            'fornecedores.*.email' => 'nullable|string|max:191',
            'fornecedores.*.proposta' => 'nullable|string|max:100',
            'fornecedores.*.condicao_pagamento.codigo' => 'nullable|string|max:20',
            'fornecedores.*.condicao_pagamento.descricao' => 'nullable|string|max:191',
            'fornecedores.*.tipo_frete' => 'nullable|string|max:10',
            'fornecedores.*.valor_frete' => 'nullable|numeric',
            'fornecedores.*.desconto' => 'nullable|numeric',
            'fornecedores.*.difal_percent' => 'nullable|numeric',
            'fornecedores.*.itens' => 'required|array',
            'fornecedores.*.itens.*.item_id' => 'required|integer|exists:purchase_quote_items,id',
            'fornecedores.*.itens.*.marca' => 'nullable|string|max:255',
            'fornecedores.*.itens.*.custo_unit' => 'nullable|numeric',
            'fornecedores.*.itens.*.ipi' => 'nullable|numeric',
            'fornecedores.*.itens.*.custo_ipi' => 'nullable|numeric',
            'fornecedores.*.itens.*.difal' => 'nullable|numeric',
            'fornecedores.*.itens.*.icms' => 'nullable|numeric',
            'fornecedores.*.itens.*.icms_total' => 'nullable|numeric',
            'fornecedores.*.itens.*.custo_final' => 'nullable|numeric',
            'selecoes' => 'array',
            'selecoes.*.item_id' => 'required|integer|exists:purchase_quote_items,id',
            'selecoes.*.supplier_id' => 'nullable|integer|exists:purchase_quote_suppliers,id',
            'selecoes.*.supplier_codigo' => 'nullable|string|max:60',
            'selecoes.*.valor_unitario' => 'nullable|numeric',
            'selecoes.*.valor_total' => 'nullable|numeric',
            'selecoes.*.motivo' => 'nullable|string',
        ]);

        $normalizeNumber = static function ($value): ?float {
            if ($value === null || $value === '') {
                return null;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }

            $normalized = str_replace(['.', ','], ['', '.'], preg_replace('/[^0-9,.-]/', '', (string) $value));

            return is_numeric($normalized) ? (float) $normalized : null;
        };

        DB::beginTransaction();

        try {
            $quote->loadMissing('items', 'suppliers');

            $supplierIdMap = [];
            $supplierCodeMap = [];
            $supplierIdsKept = [];

            foreach ($validated['fornecedores'] as $supplierPayload) {
                $supplier = null;
                if (!empty($supplierPayload['id'])) {
                    $supplier = $quote->suppliers->firstWhere('id', $supplierPayload['id']);
                }

                $isNewSupplier = false;
                if (!$supplier) {
                    $isNewSupplier = true;
                    $supplier = new PurchaseQuoteSupplier();
                    $supplier->purchase_quote_id = $quote->id;
                }

                $supplierData = [
                    'purchase_quote_id' => $quote->id,
                    'supplier_code' => $supplierPayload['codigo'] ?? null,
                    'supplier_name' => $supplierPayload['nome'],
                    'supplier_document' => $supplierPayload['cnpj'] ?? null,
                    'municipality' => !empty($supplierPayload['municipality']) ? trim($supplierPayload['municipality']) : null,
                    'state' => !empty($supplierPayload['state']) ? trim($supplierPayload['state']) : null,
                    'vendor_name' => $supplierPayload['vendedor'] ?? null,
                    'vendor_phone' => $supplierPayload['telefone'] ?? null,
                    'vendor_email' => $supplierPayload['email'] ?? null,
                    'proposal_number' => $supplierPayload['proposta'] ?? null,
                    'payment_condition_code' => data_get($supplierPayload, 'condicao_pagamento.codigo'),
                    'payment_condition_description' => data_get($supplierPayload, 'condicao_pagamento.descricao'),
                    'freight_type' => $supplierPayload['tipo_frete'] ?? null,
                    'freight_value' => $normalizeNumber($supplierPayload['valor_frete'] ?? null),
                    'discount' => $normalizeNumber($supplierPayload['desconto'] ?? null),
                    'difal_percent' => $normalizeNumber($supplierPayload['difal_percent'] ?? null),
                ];

                if ($isNewSupplier) {
                    // Usar helper para inserir com timestamps como strings
                    $supplierId = $this->insertWithStringTimestamps('purchase_quote_suppliers', $supplierData);
                    $supplier = PurchaseQuoteSupplier::findOrFail($supplierId);
                } else {
                    // Usar helper para atualizar com timestamps como strings
                    $this->updateModelWithStringTimestamps($supplier, $supplierData);
                }

                $supplierIdsKept[] = $supplier->id;
                $supplierIdMap[$supplier->id] = $supplier;
                if (!empty($supplier->supplier_code)) {
                    $supplierCodeMap[$supplier->supplier_code] = $supplier;
                }

            $itemIdsPayload = [];
                foreach ($supplierPayload['itens'] as $itemPayload) {
                    $itemId = (int) $itemPayload['item_id'];
                    $item = $quote->items->firstWhere('id', $itemId);

                    if (!$item) {
                        continue;
                    }

                    $supplierItem = PurchaseQuoteSupplierItem::firstOrNew([
                        'purchase_quote_supplier_id' => $supplier->id,
                        'purchase_quote_item_id' => $item->id,
                    ]);

                    $supplierItemData = [
                        'purchase_quote_supplier_id' => $supplier->id,
                        'purchase_quote_item_id' => $item->id,
                        'brand' => (!empty($itemPayload['marca']) && trim($itemPayload['marca']) !== '') ? trim($itemPayload['marca']) : null,
                        'unit_cost' => $normalizeNumber($itemPayload['custo_unit'] ?? null),
                        'ipi' => $normalizeNumber($itemPayload['ipi'] ?? null),
                        'unit_cost_with_ipi' => $normalizeNumber($itemPayload['custo_ipi'] ?? null),
                        'difal' => $normalizeNumber($itemPayload['difal'] ?? null),
                        'icms' => $normalizeNumber($itemPayload['icms'] ?? null),
                        'icms_total' => $normalizeNumber($itemPayload['icms_total'] ?? null),
                        'final_cost' => $normalizeNumber($itemPayload['custo_final'] ?? null),
                    ];

                    if (!$supplierItem->exists) {
                        // Usar helper para inserir com timestamps como strings
                        $supplierItemId = $this->insertWithStringTimestamps('purchase_quote_supplier_items', $supplierItemData);
                        $supplierItem = PurchaseQuoteSupplierItem::findOrFail($supplierItemId);
                    } else {
                        // Usar helper para atualizar com timestamps como strings
                        $this->updateModelWithStringTimestamps($supplierItem, $supplierItemData);
                    }
                    $itemIdsPayload[] = $item->id;
                }

                $supplier->items()
                    ->whereNotIn('purchase_quote_item_id', $itemIdsPayload)
                    ->delete();
            }

            $quote->suppliers()
                ->whereNotIn('id', $supplierIdsKept)
                ->delete();

            $selectionMap = collect($validated['selecoes'] ?? [])->keyBy(function ($item) {
                return (int) $item['item_id'];
            });

            foreach ($quote->items as $item) {
                $selection = $selectionMap->get($item->id);

                if (!$selection) {
                    $this->updateModelWithStringTimestamps($item, [
                        'selected_supplier_id' => null,
                        'selected_unit_cost' => null,
                        'selected_total_cost' => null,
                        'selection_reason' => null,
                    ]);
                    continue;
                }

                $selectedSupplierId = $selection['supplier_id'] ?? null;
                if (!$selectedSupplierId && !empty($selection['supplier_codigo']) && isset($supplierCodeMap[$selection['supplier_codigo']])) {
                    $selectedSupplierId = $supplierCodeMap[$selection['supplier_codigo']]->id;
                }

                $this->updateModelWithStringTimestamps($item, [
                    'selected_supplier_id' => $selectedSupplierId,
                    'selected_unit_cost' => $normalizeNumber($selection['valor_unitario'] ?? null),
                    'selected_total_cost' => $normalizeNumber($selection['valor_total'] ?? null),
                    'selection_reason' => $selection['motivo'] ?? null,
                ]);
            }

            $productDefaultsService->apply($quote);

            // Aprovar automaticamente o COMPRADOR quando ele salva a cotação
            // O comprador está salvando a cotação, então ele já está implicitamente aprovando
            $user = auth()->user();
            
            // Verificar se há aprovação pendente para COMPRADOR
            $buyerApproval = $quote->approvals()
                ->byLevel('COMPRADOR')
                ->required()
                ->where('approved', false)
                ->first();
            
            if ($buyerApproval) {
                try {
                    // Aprovar diretamente sem verificar permissão, pois o comprador está salvando
                    $this->updateModelWithStringTimestamps($buyerApproval, [
                        'approved' => true,
                        'approved_by' => $user->id,
                        'approved_by_name' => $user->nome_completo ?? $user->name,
                        'approved_at' => now()->format('Y-m-d H:i:s'),
                        'notes' => 'Cotação salva pelo comprador.',
                    ]);
                    
                    // Recarregar o relacionamento approver para garantir que está disponível
                    $buyerApproval->refresh();
                    $buyerApproval->load('approver');
                } catch (\Exception $e) {
                    // Log do erro mas não interrompe o fluxo
                    Log::warning('Erro ao aprovar automaticamente o COMPRADOR', [
                        'quote_id' => $quote->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $statusCompra = PurchaseQuoteStatus::where('slug', 'compra_em_andamento')->first();
            if ($statusCompra && $quote->current_status_slug !== $statusCompra->slug) {
                $this->transitionStatus($quote, $statusCompra, 'Cotação salva e compra em andamento.');
                app(PurchaseQuoteProtheusExportService::class)->resetQuote($quote);
            } else {
                // Usar helper para atualizar apenas updated_at com timestamps como strings
                $this->updateModelWithStringTimestamps($quote, []);
            }

            DB::commit();

            $quote->refresh();

            return response()->json([
                'message' => 'Cotação atualizada com sucesso.',
                'status' => [
                    'slug' => $quote->current_status_slug,
                    'label' => $quote->current_status_label,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Falha ao salvar cotação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível salvar os dados da cotação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function assignBuyer(Request $request, PurchaseQuote $quote)
    {
        // Verificar se o usuário tem permissão para atribuir comprador
        $user = auth()->user();
        if (!$user || !$user->hasPermission('cotacoes_assign_buyer')) {
            return response()->json([
                'message' => 'Você não tem permissão para atribuir comprador à cotação.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'buyer_id' => 'required|integer|exists:users,id',
            'observacao' => 'nullable|string',
        ]);

        $buyer = User::find($validated['buyer_id']);

        if (!$buyer) {
            return response()->json([
                'message' => 'Comprador informado não foi encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        $status = PurchaseQuoteStatus::where('slug', 'cotacao')->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status "cotacao" não configurado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            $this->updateModelWithStringTimestamps($quote, [
                'buyer_id' => $buyer->id,
                'buyer_name' => $buyer->nome_completo ?? $buyer->name,
                'updated_by' => auth()->id(),
            ]);

            $this->transitionStatus($quote, $status, $validated['observacao'] ?? 'Cotação vinculada a comprador.');

            // Se houver observação, salvar como mensagem (mesma lógica de reprovação)
            if (!empty($validated['observacao'])) {
                $normalizedMessage = trim((string) $validated['observacao']);
                if ($normalizedMessage !== '') {
                    $this->insertWithStringTimestamps('purchase_quote_messages', [
                        'purchase_quote_id' => $quote->id,
                        'user_id' => auth()->id(),
                        'type' => 'instrucao',
                        'message' => $normalizedMessage,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Comprador vinculado e status atualizado para cotação.',
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Falha ao vincular comprador', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível vincular a solicitação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function approve(Request $request, PurchaseQuote $quote)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verificar se o usuário tem permissão para editar na aprovação
        $hasEditPermission = $user->hasPermission('edit_cotacoes_aprovacao');
        
        // Validação base: sempre requer observacao/mensagem
        $validationRules = [
            'observacao' => 'nullable|string',
            'mensagem' => 'nullable|string',
            'status' => 'nullable|string|in:analisada,analisada_aguardando', // Status opcional para quando o status atual é "finalizada"
        ];
        
        // Se tem permissão de editar na aprovação, aceitar dados de edição opcionais
        if ($hasEditPermission) {
            $validationRules = array_merge($validationRules, [
                'numero' => 'nullable|string|max:30|unique:purchase_quotes,quote_number,' . $quote->id,
                'data_solicitacao' => 'nullable|date',
                'solicitante.id' => 'nullable|integer|exists:users,id',
                'solicitante.label' => 'nullable|string|max:191',
                'empresa.id' => 'nullable|integer|exists:companies,id',
                'empresa.label' => 'nullable|string|max:191',
                'local' => 'nullable|string|max:191',
                'work_front' => 'nullable|string|max:191',
                'itens' => 'nullable|array|min:1',
                'itens.*.id' => 'nullable|integer|exists:purchase_quote_items,id',
                'itens.*.codigo' => 'nullable|string|max:100',
                'itens.*.referencia' => 'nullable|string|max:100',
                'itens.*.mercadoria' => 'nullable|string|max:255',
                'itens.*.quantidade' => 'nullable|numeric|min:0.0001',
                'itens.*.unidade' => 'nullable|string|max:20',
                'itens.*.aplicacao' => 'nullable|string',
                'itens.*.prioridade' => 'nullable|integer|min:0',
                'itens.*.tag' => 'nullable|string|max:100',
                'itens.*.centro_custo.codigo' => 'nullable|string|max:50',
                'itens.*.centro_custo.descricao' => 'nullable|string',
                'itens.*.centro_custo.classe' => 'nullable|string|max:20',
            ]);
        }
        
        $validated = $request->validate($validationRules);
        
        $currentStatus = $quote->current_status_slug;
        $approvalService = app(PurchaseQuoteApprovalService::class);
        
        // Verificar se o diretor tem permissão "Aprovar como Diretor" e pode aprovar diretamente
        $hasDirectorPermission = $user->hasPermission('cotacoes_aprovar_diretor');
        $userLevels = $approvalService->getUserApprovalLevels($user, $quote->company_id);
        $isDirector = in_array('DIRETOR', $userLevels);
        
        // Se é diretor com permissão especial, pode aprovar diretamente APENAS se:
        // 1. A cotação tem comprador associado (buyer_id não é null)
        // 2. O status é "finalizada" ou superior (finalizada, analisada, analisada_aguardando, analise_gerencia)
        // 3. Não está aprovado ou reprovado
        $allowedStatusesForDirectApproval = ['finalizada', 'analisada', 'analisada_aguardando', 'analise_gerencia'];
        $hasBuyer = $quote->buyer_id !== null;
        
        if ($hasDirectorPermission && $isDirector && $hasBuyer && 
            in_array($currentStatus, $allowedStatusesForDirectApproval, true)) {
            $nextStatusSlug = 'aprovado';
            $defaultNote = 'Cotação aprovada diretamente pelo diretor.';
        }
        // Verificar permissão baseada no status atual (lógica normal)
        elseif ($currentStatus === 'aguardando') {
            // Para autorizar, verificar permissão específica
            if (!$user->hasPermission('cotacoes_autorizar')) {
                return response()->json([
                    'message' => 'Você não tem permissão para autorizar solicitações de cotação.',
                ], Response::HTTP_FORBIDDEN);
            }
            $nextStatusSlug = 'autorizado';
            $defaultNote = 'Solicitação autorizada para cotação.';
        } elseif (in_array($currentStatus, ['finalizada', 'analisada', 'analisada_aguardando'], true)) {
            // Para aprovar análise, verificar permissão genérica ou nível de aprovação
            $hasGenericPermission = $user->hasPermission('cotacoes_aprovar');
            $approvalService = app(PurchaseQuoteApprovalService::class);
            $nextLevel = $approvalService->getNextPendingLevelForUser($quote, $user);
            
            // Se não encontrou nível pendente, verificar se o usuário tem algum dos níveis intermediários
            // e se há aprovações pendentes para esses níveis (para status "finalizada")
            if ($nextLevel === null && $currentStatus === 'finalizada') {
                $userLevels = $approvalService->getUserApprovalLevels($user, $quote->company_id);
                $intermediateLevels = ['ENGENHEIRO', 'GERENTE_LOCAL', 'GERENTE_GERAL'];
                $userIntermediateLevels = array_intersect($userLevels, $intermediateLevels);
                
                if (!empty($userIntermediateLevels)) {
                    // Verificar se há aprovações pendentes para algum dos níveis intermediários do usuário
                    $pendingIntermediateApprovals = $quote->approvals()
                        ->required()
                        ->pending()
                        ->whereIn('approval_level', $userIntermediateLevels)
                        ->get();
                    
                    if ($pendingIntermediateApprovals->isNotEmpty()) {
                        // Priorizar o nível de maior hierarquia quando o usuário tem múltiplos níveis
                        // Ordem de prioridade: GERENTE_GERAL > GERENTE_LOCAL > ENGENHEIRO
                        $priorityOrder = ['GERENTE_GERAL' => 1, 'GERENTE_LOCAL' => 2, 'ENGENHEIRO' => 3];
                        $sortedApprovals = $pendingIntermediateApprovals->sortBy(function ($approval) use ($priorityOrder) {
                            return $priorityOrder[$approval->approval_level] ?? 999;
                        });
                        
                        foreach ($sortedApprovals as $approval) {
                            if ($approvalService->userHasLevelPermission($user, $approval->approval_level)) {
                                $nextLevel = $approval->approval_level;
                                break;
                            }
                        }
                    } else {
                        // Se não há aprovações pendentes, verificar se o usuário pode aprovar algum dos seus níveis
                        // Priorizar o nível de maior hierarquia quando o usuário tem múltiplos níveis
                        $priorityOrder = ['GERENTE_GERAL' => 1, 'GERENTE_LOCAL' => 2, 'ENGENHEIRO' => 3];
                        $sortedUserLevels = collect($userIntermediateLevels)->sortBy(function ($level) use ($priorityOrder) {
                            return $priorityOrder[$level] ?? 999;
                        });
                        
                        foreach ($sortedUserLevels as $level) {
                            if ($approvalService->userHasLevelPermission($user, $level)) {
                                // Verificar se já existe uma aprovação para esse nível (mesmo que aprovada)
                                $existingApproval = $quote->approvals()
                                    ->byLevel($level)
                                    ->first();
                                
                                // Se não existe, criar a aprovação automaticamente
                                if (!$existingApproval) {
                                    $order = $approvalService->getApprovalOrder();
                                    $this->insertWithStringTimestamps('purchase_quote_approvals', [
                                        'purchase_quote_id' => $quote->id,
                                        'approval_level' => $level,
                                        'required' => true,
                                        'approved' => false,
                                        'order' => $order[$level] ?? 999,
                                    ]);
                                    $quote->refresh();
                                }
                                
                                // Se não existe ou não está aprovada, usar esse nível
                                if (!$existingApproval || !$existingApproval->approved) {
                                    $nextLevel = $level;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            
            if (!$hasGenericPermission && $nextLevel === null) {
                return response()->json([
                    'message' => 'Você não tem permissão para aprovar esta solicitação ou não há aprovações pendentes no seu nível.',
                ], Response::HTTP_FORBIDDEN);
            }
            
            // Não definir nextStatusSlug ainda - será determinado após salvar a assinatura
            // Verificaremos se o nível que está aprovando é o último antes do DIRETOR
            $nextStatusSlug = null; // Será determinado depois
            $defaultNote = 'Assinatura registrada.';
        } elseif ($currentStatus === 'analise_gerencia') {
            // Para aprovar na gerência, verificar permissão genérica ou nível de aprovação
            $hasGenericPermission = $user->hasPermission('cotacoes_aprovar');
            $approvalService = app(PurchaseQuoteApprovalService::class);
            $nextLevel = $approvalService->getNextPendingLevelForUser($quote, $user);
            
            if (!$hasGenericPermission && $nextLevel === null) {
                return response()                   ->json([
                    'message' =>                     'Você não tem permissão para aprovar esta solicitação ou não há aprovações pendentes no seu nível.',
                ], Response::HTTP_FORBIDDEN);
            }
            $nextStatusSlug = 'aprovado';
            $defaultNote = 'Cotação aprovada pela gerência.';
        } else {
            return response()->json([
                'message' => 'O status atual da solicitação não permite aprovação.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $note = $validated['observacao'] ?? $defaultNote;

        DB::beginTransaction();

        try {
            // Se tem permissão de editar na aprovação e foram enviados dados de edição, aplicar as mudanças primeiro
            $hasEditData = $hasEditPermission && (
                !empty($validated['itens']) ||
                isset($validated['numero']) ||
                isset($validated['data_solicitacao']) ||
                isset($validated['solicitante']) ||
                isset($validated['empresa']) ||
                isset($validated['local']) ||
                isset($validated['observacao'])
            );
            
            if ($hasEditData) {
                // Aplicar edições antes de aprovar
                $this->applyQuoteEditions($quote, $validated);
                $quote->refresh();
            }
            
            // Se o status é "finalizada", "analisada" ou "analisada_aguardando", primeiro aprovar o nível do usuário
            if (in_array($currentStatus, ['finalizada', 'analisada', 'analisada_aguardando'], true) && $nextStatusSlug === null) {
                // Se o status atual é "finalizada" e foi enviado um status desejado, mudar o status primeiro
                if ($currentStatus === 'finalizada' && !empty($validated['status'])) {
                    $desiredStatus = PurchaseQuoteStatus::where('slug', $validated['status'])->first();
                    if ($desiredStatus) {
                        $this->transitionStatus($quote, $desiredStatus, 'Status atualizado para análise.');
                        $quote->refresh();
                        $currentStatus = $quote->current_status_slug; // Atualizar o status atual
                    }
                }
                
                // Aprovar o nível do usuário primeiro
                if ($nextLevel) {
                    // Verificar se a aprovação existe, se não existir, criar
                    $approval = $quote->approvals()
                        ->byLevel($nextLevel)
                        ->first();
                    
                    if (!$approval) {
                        // Criar aprovação se não existir
                        $order = $approvalService->getApprovalOrder();
                        $this->insertWithStringTimestamps('purchase_quote_approvals', [
                            'purchase_quote_id' => $quote->id,
                            'approval_level' => $nextLevel,
                            'required' => true,
                            'approved' => false,
                            'order' => $order[$nextLevel] ?? 999,
                        ]);
                        $quote->refresh();
                    }
                    
                    // Agora aprovar o nível
                    $approvalService->approveLevel($quote, $nextLevel, $user, $note);
                    $quote->refresh();
                }
                
                // Identificar qual é o último nível antes do DIRETOR que está requerido
                $order = $approvalService->getApprovalOrder();
                $directorOrder = $order['DIRETOR'] ?? 999;
                
                // Buscar todos os níveis intermediários requeridos (antes do DIRETOR)
                $intermediateApprovals = $quote->approvals()
                    ->required()
                    ->get()
                    ->filter(function ($approval) use ($order, $directorOrder) {
                        $approvalOrder = $order[$approval->approval_level] ?? 999;
                        return $approvalOrder < $directorOrder; // Apenas níveis antes do DIRETOR
                    });
                
                // Se não há níveis intermediários, pode ir direto para DIRETOR
                if ($intermediateApprovals->isEmpty()) {
                    $nextStatusSlug = 'analise_gerencia';
                    $defaultNote = 'Cotação encaminhada para análise da gerência.';
                    $note = $validated['observacao'] ?? $defaultNote;
                } else {
                    // Verificar se todos os níveis intermediários foram aprovados
                    // Como ENGENHEIRO, GERENTE_LOCAL e GERENTE_GERAL têm a mesma ordem (2),
                    // não há "último" - todos são simultâneos
                    $allIntermediateApproved = $intermediateApprovals->every(function ($approval) {
                        return $approval->approved;
                    });
                    
                    // Se todos os níveis intermediários foram aprovados, mudar status para DIRETOR
                    if ($allIntermediateApproved) {
                        $nextStatusSlug = 'analise_gerencia';
                        $defaultNote = 'Cotação encaminhada para análise da gerência.';
                        $note = $validated['observacao'] ?? $defaultNote;
                    } else {
                        // Manter o status atual - não mudar status, apenas a assinatura já foi salva
                        // Registrar no histórico que a assinatura foi registrada
                        $currentStatusObj = PurchaseQuoteStatus::where('slug', $currentStatus)->first();
                        if ($currentStatusObj) {
                            $this->insertWithStringTimestamps('purchase_quote_status_histories', [
                                'purchase_quote_id' => $quote->id,
                                'status_id' => $currentStatusObj->id,
                                'status_slug' => $currentStatusObj->slug,
                                'status_label' => $currentStatusObj->label,
                                'acted_by' => $user->id,
                                'acted_by_name' => $user->nome_completo ?? $user->name,
                                'notes' => $validated['observacao'] ?? 'Assinatura registrada.',
                            ]);
                        }
                        
                        DB::commit();
                        
                        return response()->json([
                            'message' => 'Assinatura registrada com sucesso.',
                            'status' => [
                                'slug' => $currentStatus,
                                'label' => $currentStatusObj->label ?? $currentStatus,
                            ],
                        ]);
                    }
                }
            }
            
            // Buscar o status apenas agora que foi determinado
            $status = PurchaseQuoteStatus::where('slug', $nextStatusSlug)->first();

            if (!$status) {
                return response()->json([
                    'message' => sprintf('Status "%s" não configurado.', $nextStatusSlug),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            
            // Se é diretor com permissão especial, preencher automaticamente todas as assinaturas intermediárias
            if ($hasDirectorPermission && $isDirector && $status->slug === 'aprovado') {
                // Preencher automaticamente todas as assinaturas intermediárias pendentes (exceto DIRETOR)
                $order = $approvalService->getApprovalOrder();
                $pendingApprovals = $quote->approvals()
                    ->required()
                    ->where('approved', false)
                    ->where('approval_level', '!=', 'DIRETOR')
                    ->get();
                
                $directorOrder = $order['DIRETOR'] ?? 999;
                
                foreach ($pendingApprovals as $approval) {
                    $approvalOrder = $order[$approval->approval_level] ?? 999;
                    // Aprovar apenas níveis anteriores ao DIRETOR
                    if ($approvalOrder < $directorOrder) {
                        $this->updateModelWithStringTimestamps($approval, [
                            'approved' => true,
                            'approved_by' => $user->id,
                            'approved_by_name' => $user->nome_completo ?? $user->name,
                            'approved_at' => now()->format('Y-m-d H:i:s'),
                            'notes' => 'Aprovado automaticamente pelo diretor.',
                        ]);
                    }
                }
                
                // Agora aprovar o DIRETOR
                $directorApproval = $quote->approvals()
                    ->byLevel('DIRETOR')
                    ->required()
                    ->where('approved', false)
                    ->first();
                
                if ($directorApproval) {
                    $this->updateModelWithStringTimestamps($directorApproval, [
                        'approved' => true,
                        'approved_by' => $user->id,
                        'approved_by_name' => $user->nome_completo ?? $user->name,
                        'approved_at' => now()->format('Y-m-d H:i:s'),
                        'notes' => $note,
                    ]);
                }
            }
            
            $this->transitionStatus($quote, $status, $note);

            if ($status->slug === 'aprovado') {
                app(PurchaseQuoteProtheusExportService::class)->prepareQuote($quote);
                
                // Criar pedidos de compra (um por fornecedor selecionado)
                try {
                    app(\App\Services\PurchaseOrderService::class)->criarPedidosPorCotacao($quote);
                } catch (\Exception $e) {
                    Log::warning('Erro ao criar pedidos de compra', [
                        'quote_id' => $quote->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Não interrompe o fluxo se houver erro nos pedidos
                }
                
                // Entrada de produtos no estoque
                try {
                    app(\App\Services\StockPurchaseService::class)->entrarProdutosPorCompra($quote);
                } catch (\Exception $e) {
                    Log::warning('Erro ao entrar produtos no estoque', [
                        'quote_id' => $quote->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Não interrompe o fluxo se houver erro no estoque
                }
                // Criação de ativos
                try {
                    app(\App\Services\AssetPurchaseService::class)->criarAtivosPorCompra($quote);
                } catch (\Exception $e) {
                    Log::warning('Erro ao criar ativos', [
                        'quote_id' => $quote->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Não interrompe o fluxo se houver erro nos ativos
                }
            }
            DB::commit();

            return response()->json([
                'message' => 'Solicitação aprovada.',
                'status' => [
                    'slug' => $status->slug,
                    'label' => $status->label,
                ],
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Falha ao aprovar solicitação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível aprovar a solicitação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reject(Request $request, PurchaseQuote $quote)
    {
        $validated = $request->validate([
            'observacao' => 'nullable|string',
            'mensagem' => 'nullable|string',
        ]);

        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Verificar permissão para rejeitar/reprovar
        // Verificar se tem permissão de rejeitar (cotacoes_rejeitar) ou reprovar (cotacoes_reprovar)
        $hasRejectPermission = $user->hasPermission('cotacoes_rejeitar');
        $hasReprovePermission = $user->hasPermission('cotacoes_reprovar');
        
        if (!$hasRejectPermission && !$hasReprovePermission) {
            return response()->json([
                'message' => 'Você não tem permissão para rejeitar/reprovar solicitações.',
            ], Response::HTTP_FORBIDDEN);
        }

        $normalizedMessage = trim((string) ($validated['mensagem'] ?? $validated['observacao'] ?? ''));

        // Se o diretor está reprovando (status "aprovado" ou "analise_gerencia"), limpar assinaturas exceto COMPRADOR
        $isDirectorRejecting = in_array($quote->current_status_slug, ['aprovado', 'analise_gerencia'], true);
        $approvalService = app(PurchaseQuoteApprovalService::class);
        $userLevels = $approvalService->getUserApprovalLevels($user, $quote->company_id);
        $isDirector = in_array('DIRETOR', $userLevels);

        if ($isDirectorRejecting && $isDirector) {
            if ($normalizedMessage === '') {
                return response()->json([
                    'message' => 'Informe o motivo da reprovação.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $statusCotacao = PurchaseQuoteStatus::where('slug', 'cotacao')->first();

            if (!$statusCotacao) {
                return response()->json([
                    'message' => 'Status "cotacao" não configurado. Execute as migrations novamente.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();

            try {
                // Limpar todas as assinaturas exceto COMPRADOR
                $quote->approvals()
                    ->where('approval_level', '!=', 'COMPRADOR')
                    ->update([
                        'approved' => false,
                        'approved_by' => null,
                        'approved_by_name' => null,
                        'approved_at' => null,
                        'notes' => null,
                    ]);

                // Inserir mensagem de reprovação
                $this->insertWithStringTimestamps('purchase_quote_messages', [
                    'purchase_quote_id' => $quote->id,
                    'user_id' => auth()->id(),
                    'type' => 'reprova',
                    'message' => $normalizedMessage,
                ]);

                $this->transitionStatus($quote, $statusCotacao, $normalizedMessage);

                DB::commit();

                return response()->json([
                    'message' => 'Cotação reprovada pelo diretor e retornada para cotação. Todas as assinaturas foram removidas exceto a do comprador.',
                    'status' => [
                        'slug' => $statusCotacao->slug,
                        'label' => $statusCotacao->label,
                    ],
                ]);
            } catch (\Throwable $exception) {
                DB::rollBack();

                Log::error('Falha ao reprovar cotação pelo diretor', [
                    'quote_id' => $quote->id,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Não foi possível reprovar a cotação.',
                    'error' => $exception->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if ($quote->current_status_slug === 'finalizada') {
            if ($normalizedMessage === '') {
                return response()->json([
                    'message' => 'Informe o motivo da reprovação.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $statusRetorno = PurchaseQuoteStatus::where('slug', 'compra_em_andamento')->first();

            if (!$statusRetorno) {
                return response()->json([
                    'message' => 'Status "compra_em_andamento" não configurado. Execute as migrations novamente.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();

            try {
                $this->updateModelWithStringTimestamps($quote, [
                    'requires_response' => true,
                    'updated_by' => auth()->id(),
                ]);

                // Usar helper para inserir com timestamps como strings
                $this->insertWithStringTimestamps('purchase_quote_messages', [
                    'purchase_quote_id' => $quote->id,
                    'user_id' => auth()->id(),
                    'type' => 'reprova',
                    'message' => $normalizedMessage,
                ]);

                $this->transitionStatus($quote, $statusRetorno, 'Cotação retornada ao comprador para ajustes.');

                DB::commit();

                return response()->json([
                    'message' => 'Cotação reprovada e retornada ao comprador.',
                    'status' => [
                        'slug' => $statusRetorno->slug,
                        'label' => $statusRetorno->label,
                    ],
                ], Response::HTTP_OK);
            } catch (\Throwable $exception) {
                DB::rollBack();

                Log::error('Falha ao reprovar cotação', [
                    'quote_id' => $quote->id,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Não foi possível reprovar a cotação.',
                    'error' => $exception->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if ($quote->current_status_slug !== 'aguardando') {
            return response()->json([
                'message' => 'Somente solicitações aguardando podem ser reprovadas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar que a mensagem foi fornecida
        if ($normalizedMessage === '') {
            return response()->json([
                'message' => 'Informe o motivo da reprovação.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = PurchaseQuoteStatus::where('slug', 'reprovado')->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status "reprovado" não configurado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            // Criar mensagem de reprovação para o solicitante ver ao editar
            $this->insertWithStringTimestamps('purchase_quote_messages', [
                'purchase_quote_id' => $quote->id,
                'user_id' => auth()->id(),
                'type' => 'reprova',
                'message' => $normalizedMessage,
            ]);

            $this->transitionStatus($quote, $status, $normalizedMessage);
            DB::commit();

            return response()->json([
                'message' => 'Solicitação reprovada.',
                'status' => [
                    'slug' => $status->slug,
                    'label' => $status->label,
                ],
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Falha ao reprovar solicitação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível reprovar a solicitação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function finalizeQuote(Request $request, PurchaseQuote $quote)
    {
        $validated = $request->validate([
            'observacao' => 'nullable|string',
            'mensagem' => 'nullable|string'
        ]);

        if ($quote->current_status_slug !== 'compra_em_andamento') {
            return response()->json([
                'message' => 'Somente cotações em andamento podem ser finalizadas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = PurchaseQuoteStatus::where('slug', 'finalizada')->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status "finalizada" não configurado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            $normalizedMessage = trim((string) ($validated['mensagem'] ?? ''));

            if ($quote->requires_response && $normalizedMessage === '') {
                return response()->json([
                    'message' => 'Informe uma mensagem de retorno antes de finalizar a cotação.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->updateModelWithStringTimestamps($quote, [
                'updated_by' => auth()->id(),
                'requires_response' => false,
            ]);

            $this->transitionStatus($quote, $status, $validated['observacao'] ?? 'Cotação finalizada.');

            if ($normalizedMessage !== '') {
                // Usar helper para inserir com timestamps como strings
                $this->insertWithStringTimestamps('purchase_quote_messages', [
                    'purchase_quote_id' => $quote->id,
                    'user_id' => auth()->id(),
                    'type' => 'response',
                    'message' => $normalizedMessage,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Cotação finalizada com sucesso.',
                'status' => [
                    'slug' => $status->slug,
                    'label' => $status->label,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Falha ao finalizar cotação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível finalizar a cotação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function analyzeQuote(Request $request, PurchaseQuote $quote)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        $validated = $request->validate([
            'status' => 'required|string|in:analisada,analisada_aguardando,analise_gerencia,aprovado',
            'observacao' => 'nullable|string',
        ]);
        
        // Verificar permissão baseada no contexto (após validação)
        $currentStatus = $quote->current_status_slug;
        $requestedStatus = $validated['status'];
        
        // Se está tentando aprovar como Diretor (status atual é analise_gerencia e status desejado é aprovado)
        if ($currentStatus === 'analise_gerencia' && $requestedStatus === 'aprovado') {
            // Verificar através do ApprovalService se o usuário pode aprovar como DIRETOR
            $approvalService = app(PurchaseQuoteApprovalService::class);
            
            // Verificar se pode aprovar o nível DIRETOR através do ApprovalService
            $directorApproval = $quote->approvals()
                ->byLevel('DIRETOR')
                ->required()
                ->where('approved', false)
                ->first();
            
            if ($directorApproval) {
                // Se há aprovação pendente, verificar se pode aprovar através do ApprovalService
                $canApproveLevel = $approvalService->canApproveLevel($quote, 'DIRETOR', $user);
                
                if (!$canApproveLevel) {
                    // Se não pode aprovar através do ApprovalService, verificar permissões diretas como fallback
                    $hasDirectorPermission = $user->hasPermission('cotacoes_aprovar_diretor');
                    $hasGenericPermission = $user->hasPermission('cotacoes_aprovar');
                    
                    if (!$hasDirectorPermission && !$hasGenericPermission) {
                        return response()->json([
                            'message' => 'Você não tem permissão para aprovar esta cotação.',
                        ], Response::HTTP_FORBIDDEN);
                    }
                }
            } else {
                // Se não há aprovação pendente, verificar permissões diretas
                $hasDirectorPermission = $user->hasPermission('cotacoes_aprovar_diretor');
                $hasGenericPermission = $user->hasPermission('cotacoes_aprovar');
                
                if (!$hasDirectorPermission && !$hasGenericPermission) {
                    return response()->json([
                        'message' => 'Você não tem permissão para aprovar esta cotação.',
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        } else {
            // Para outros casos, verificar permissão genérica de analisar
            if (!$user->hasPermission('cotacoes_analisar')) {
                return response()->json([
                    'message' => 'Você não tem permissão para analisar esta cotação.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $transitions = [
            'finalizada' => [
                'analisada' => 'Cotação analisada pelo supervisor de compras.',
                'analisada_aguardando' => 'Cotação analisada e aguardando momento da compra.',
            ],
            'analisada' => [
                'analise_gerencia' => 'Cotação encaminhada para análise da gerência.',
            ],
            'analisada_aguardando' => [
                'analise_gerencia' => 'Cotação encaminhada para análise da gerência.',
            ],
            'analise_gerencia' => [
                'aprovado' => 'Cotação aprovada pela diretoria.',
            ],
        ];

        if (!isset($transitions[$currentStatus])) {
            return response()->json([
                'message' => 'O status atual da cotação não permite essa análise.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!array_key_exists($validated['status'], $transitions[$currentStatus])) {
            return response()->json([
                'message' => 'A transição de status solicitada não é permitida para o estágio atual.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = PurchaseQuoteStatus::where('slug', $validated['status'])->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status solicitado não está configurado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $defaultNote = $transitions[$currentStatus][$validated['status']];

        DB::beginTransaction();

        try {
            // Garantir que a cotação tenha company_id (pegar do header se não tiver)
            if (!$quote->company_id) {
                $companyId = (int) $request->header('company-id');
                if ($companyId) {
                    $this->updateModelWithStringTimestamps($quote, [
                        'company_id' => $companyId,
                    ]);
                    $quote->refresh();
                }
            }

            $this->updateModelWithStringTimestamps($quote, [
                'updated_by' => auth()->id(),
            ]);

            $this->transitionStatus(
                $quote,
                $status,
                $validated['observacao'] ?? $defaultNote
            );

            // Processar aprovações baseadas no status e no perfil do usuário
            $user = auth()->user();
            $approvalService = app(PurchaseQuoteApprovalService::class);
            
            // Se o status atual é 'finalizada' e está mudando para 'analisada', verificar qual perfil está analisando
            // Isso garante que quando o Gerente Local ou Engenheiro analisam uma cotação finalizada, suas aprovações sejam registradas
            if ($currentStatus === 'finalizada' && $status->slug === 'analisada') {
                // Verificar se há aprovação pendente para GERENTE_LOCAL e o usuário pode aprovar
                $localManagerApproval = $quote->approvals()
                    ->byLevel('GERENTE_LOCAL')
                    ->required()
                    ->where('approved', false)
                    ->first();

                // Verificar se pode aprovar (após garantir company_id)
                $canApprove = $approvalService->canApproveLevel($quote, 'GERENTE_LOCAL', $user);
                
                if ($localManagerApproval && $canApprove) {
                    // Aprovar o nível GERENTE_LOCAL
                    $approvalService->approveLevel($quote, 'GERENTE_LOCAL', $user, $validated['observacao'] ?? null);
                    
                    // Recarregar a cotação com os relacionamentos atualizados para garantir que a assinatura apareça
                    $quote->refresh();
                    $quote->load(['approvals.approver']);
                }
                
                // Verificar se há aprovação pendente para ENGENHEIRO e o usuário pode aprovar
                $engineerApproval = $quote->approvals()
                    ->byLevel('ENGENHEIRO')
                    ->required()
                    ->where('approved', false)
                    ->first();
                
                if ($engineerApproval && $approvalService->canApproveLevel($quote, 'ENGENHEIRO', $user)) {
                    // Aprovar o nível ENGENHEIRO
                    $approved = $approvalService->approveLevel($quote, 'ENGENHEIRO', $user, $validated['observacao'] ?? null);
                    
                    // Recarregar a cotação com os relacionamentos atualizados
                    $quote->refresh();
                    $quote->load(['approvals.approver']);
                    
                    // Verificar se há GERENTE_LOCAL pendente e avançar para ele
                    $localManagerApprovalAfterEngineer = $quote->approvals()
                        ->byLevel('GERENTE_LOCAL')
                        ->required()
                        ->where('approved', false)
                        ->first();
                    
                    if ($localManagerApprovalAfterEngineer) {
                        // Avançar para análise de gerência (onde o Gerente Local vai aprovar)
                        $statusGerencia = PurchaseQuoteStatus::where('slug', 'analise_gerencia')->first();
                        if ($statusGerencia && $quote->current_status_slug !== 'analise_gerencia') {
                            $this->transitionStatus($quote, $statusGerencia, 'Engenheiro aprovou. Aguardando análise do Gerente Local.');
                        }
                    }
                }
            } elseif ($currentStatus === 'analise_gerencia' && $status->slug === 'aprovado') {
                // Quando o status atual é 'analise_gerencia' e muda para 'aprovado',
                // o Diretor está aprovando, então deve registrar a aprovação do DIRETOR
                $directorApproval = $quote->approvals()
                    ->byLevel('DIRETOR')
                    ->required()
                    ->where('approved', false)
                    ->first();
                
                if ($directorApproval && $approvalService->canApproveLevel($quote, 'DIRETOR', $user)) {
                    // Aprovar o nível DIRETOR
                    $approvalService->approveLevel($quote, 'DIRETOR', $user, $validated['observacao'] ?? null);
                    
                    // Recarregar a cotação com os relacionamentos atualizados
                    $quote->refresh();
                    $quote->load(['approvals.approver']);
                }
            } elseif ($status->slug === 'analise_gerencia') {
                // Quando o status atual é 'analisada' ou 'analisada_aguardando' e muda para 'analise_gerencia',
                // pode ser GERENTE_LOCAL ou GERENTE_GERAL que está encaminhando, então deve aprovar automaticamente
                if (in_array($currentStatus, ['analisada', 'analisada_aguardando'])) {
                    // Verificar se há aprovação pendente para GERENTE_LOCAL e o usuário pode aprovar
                    $localManagerApproval = $quote->approvals()
                        ->byLevel('GERENTE_LOCAL')
                        ->required()
                        ->where('approved', false)
                        ->first();
                    
                    if ($localManagerApproval && $approvalService->canApproveLevel($quote, 'GERENTE_LOCAL', $user)) {
                        // Aprovar o nível GERENTE_LOCAL quando encaminha para análise de gerência
                        $approvalService->approveLevel($quote, 'GERENTE_LOCAL', $user, $validated['observacao'] ?? null);
                        
                        // Recarregar a cotação com os relacionamentos atualizados para garantir que a assinatura apareça
                        $quote->refresh();
                        $quote->load(['approvals.approver']);
                    }
                    
                    // Verificar se há aprovação pendente para GERENTE_GERAL e o usuário pode aprovar
                    $generalManagerApproval = $quote->approvals()
                        ->byLevel('GERENTE_GERAL')
                        ->required()
                        ->where('approved', false)
                        ->first();
                    
                    if ($generalManagerApproval && $approvalService->canApproveLevel($quote, 'GERENTE_GERAL', $user)) {
                        // Aprovar o nível GERENTE_GERAL quando encaminha para análise de gerência
                        $approvalService->approveLevel($quote, 'GERENTE_GERAL', $user, $validated['observacao'] ?? null);
                        
                        // Recarregar a cotação com os relacionamentos atualizados para garantir que a assinatura apareça
                        $quote->refresh();
                        $quote->load(['approvals.approver']);
                    }
                }
            } elseif ($status->slug === 'aprovado') {
                // Verificar se há aprovação pendente para GERENTE_GERAL ou DIRETOR
                // Quando o status muda para 'aprovado', o usuário que está analisando está implicitamente aprovando
                $generalManagerApproval = $quote->approvals()
                    ->byLevel('GERENTE_GERAL')
                    ->required()
                    ->where('approved', false)
                    ->first();
                
                if ($generalManagerApproval) {
                    // Aprovar GERENTE_GERAL diretamente, pois o usuário está analisando e mudando status para aprovado
                    try {
                        $this->updateModelWithStringTimestamps($generalManagerApproval, [
                            'approved' => true,
                            'approved_by' => $user->id,
                            'approved_by_name' => $user->nome_completo ?? $user->name,
                            'approved_at' => now()->format('Y-m-d H:i:s'),
                            'notes' => $validated['observacao'] ?? 'Cotação aprovada pelo Gerente Geral.',
                        ]);
                        // Garantir que o relacionamento approver está carregado
                        $generalManagerApproval->refresh();
                        $generalManagerApproval->load('approver');
                    } catch (\Exception $e) {
                        Log::warning('Erro ao aprovar automaticamente o GERENTE_GERAL', [
                            'quote_id' => $quote->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // Se não há GERENTE_GERAL pendente, verificar DIRETOR
                    $directorApproval = $quote->approvals()
                        ->byLevel('DIRETOR')
                        ->required()
                        ->where('approved', false)
                        ->first();
                    
                    if ($directorApproval) {
                        // Aprovar DIRETOR diretamente, pois o usuário está analisando e mudando status para aprovado
                        try {
                            $this->updateModelWithStringTimestamps($directorApproval, [
                                'approved' => true,
                                'approved_by' => $user->id,
                                'approved_by_name' => $user->nome_completo ?? $user->name,
                                'approved_at' => now()->format('Y-m-d H:i:s'),
                                'notes' => $validated['observacao'] ?? 'Cotação aprovada pelo Diretor.',
                            ]);
                            // Garantir que o relacionamento approver está carregado
                            $directorApproval->refresh();
                            $directorApproval->load('approver');
                        } catch (\Exception $e) {
                            Log::warning('Erro ao aprovar automaticamente o DIRETOR', [
                                'quote_id' => $quote->id,
                                'user_id' => $user->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Se houver observação, salvar como mensagem (mesma lógica de reprovação e vinculação)
            if (!empty($validated['observacao'])) {
                $normalizedMessage = trim((string) $validated['observacao']);
                if ($normalizedMessage !== '') {
                    $this->insertWithStringTimestamps('purchase_quote_messages', [
                        'purchase_quote_id' => $quote->id,
                        'user_id' => auth()->id(),
                        'type' => 'instrucao',
                        'message' => $normalizedMessage,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Cotação atualizada com sucesso.',
                'status' => [
                    'slug' => $status->slug,
                    'label' => $status->label,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Falha ao analisar cotação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível atualizar o status da cotação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reprove(Request $request, PurchaseQuote $quote)
    {
        $validated = $request->validate([
            'mensagem' => 'required|string',
        ]);

        $currentStatus = $quote->current_status_slug;
        $allowedReproveStatuses = ['finalizada', 'analisada', 'analisada_aguardando', 'analise_gerencia', 'aprovado'];

        if (!in_array($currentStatus, $allowedReproveStatuses, true)) {
            return response()->json([
                'message' => 'Somente cotações em análise podem ser reprovadas para ajustes.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = PurchaseQuoteStatus::where('slug', 'compra_em_andamento')->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status "compra_em_andamento" não configurado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            $this->updateModelWithStringTimestamps($quote, [
                'requires_response' => true,
                'updated_by' => auth()->id(),
            ]);

            // Usar helper para inserir com timestamps como strings
            $this->insertWithStringTimestamps('purchase_quote_messages', [
                'purchase_quote_id' => $quote->id,
                'user_id' => auth()->id(),
                'type' => 'reprova',
                'message' => $validated['mensagem'],
            ]);

            $this->transitionStatus($quote, $status, 'Cotação retornada ao comprador para ajustes.');
            app(PurchaseQuoteProtheusExportService::class)->resetQuote($quote);

            DB::commit();

            return response()->json([
                'message' => 'Cotação enviada ao comprador com a observação informada.',
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Falha ao reprovar cotação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível reprovar a cotação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Analisar e selecionar níveis de aprovação
     */
    public function analyzeAndSelectApprovals(Request $request, PurchaseQuote $quote)
    {
        // Verificar se o usuário tem permissão para selecionar níveis de aprovação
        $user = auth()->user();
        if (!$user || !$user->hasPermission('cotacoes_analisar_selecionar')) {
            return response()->json([
                'message' => 'Você não tem permissão para selecionar níveis de aprovação.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'niveis_aprovacao' => 'required|array|min:1',
            'niveis_aprovacao.*' => 'required|string|in:COMPRADOR,GERENTE_LOCAL,ENGENHEIRO,GERENTE_GERAL,DIRETOR,PRESIDENTE',
            'observacao' => 'nullable|string',
        ]);

        // Verificar se a cotação está no status correto
        // Permite quando está em: aguardando, em_analise_supervisor, autorizado ou cotacao
        $allowedStatuses = ['aguardando', 'em_analise_supervisor', 'autorizado', 'cotacao'];
        if (!in_array($quote->current_status_slug, $allowedStatuses, true)) {
            return response()->json([
                'message' => 'A cotação não está em um status que permite análise de aprovações.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $approvalService = app(PurchaseQuoteApprovalService::class);
        $status = PurchaseQuoteStatus::where('slug', 'em_analise_supervisor')->first();

        if (!$status) {
            return response()->json([
                'message' => 'Status "em_analise_supervisor" não configurado. Execute as migrations novamente.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            // Selecionar níveis de aprovação
            $approvalService->selectRequiredApprovals($quote, $validated['niveis_aprovacao']);

            // Se já está em "autorizado" ou "cotacao", apenas salva as aprovações sem mudar status
            if (in_array($quote->current_status_slug, ['autorizado', 'cotacao'], true)) {
                // Apenas atualizar a observação se fornecida
                if (!empty($validated['observacao'])) {
                    $this->updateModelWithStringTimestamps($quote, [
                        'updated_by' => auth()->id(),
                    ]);
                }
            } else {
                // Se está em "aguardando" ou "em_analise_supervisor", fazer a transição
                // Atualizar status para em_analise_supervisor
                $this->transitionStatus($quote, $status, $validated['observacao'] ?? 'Níveis de aprovação definidos pelo supervisor.');

                // Próximo status: autorizado (vai para comprador)
                $nextStatus = PurchaseQuoteStatus::where('slug', 'autorizado')->first();
                if ($nextStatus) {
                    $this->transitionStatus($quote, $nextStatus, 'Solicitação autorizada para cotação após análise do supervisor.');
                }
            }

            // NOTA: A mensagem já é salva no método assignBuyer quando o comprador é vinculado.
            // Não salvar novamente aqui para evitar duplicação.

            DB::commit();

            return response()->json([
                'message' => 'Níveis de aprovação selecionados com sucesso.',
                'niveis_selecionados' => $validated['niveis_aprovacao'],
                'status' => [
                    'slug' => $quote->fresh()->current_status_slug,
                    'label' => $quote->fresh()->current_status_label,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Falha ao selecionar níveis de aprovação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível selecionar os níveis de aprovação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Aprovar cotação por nível hierárquico
     */
    public function approveByLevel(Request $request, PurchaseQuote $quote, string $level)
    {
        $validated = $request->validate([
            'observacao' => 'nullable|string',
        ]);

        $validLevels = ['COMPRADOR', 'GERENTE_LOCAL', 'ENGENHEIRO', 'GERENTE_GERAL', 'DIRETOR', 'PRESIDENTE'];
        
        if (!in_array($level, $validLevels)) {
            return response()->json([
                'message' => 'Nível de aprovação inválido.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Usuário não autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Mapear nível para permissão específica
        $levelPermissionMap = [
            'COMPRADOR' => 'cotacoes_aprovar_comprador',
            'GERENTE_LOCAL' => 'cotacoes_aprovar_gerente_local',
            'ENGENHEIRO' => 'cotacoes_aprovar_engenheiro',
            'GERENTE_GERAL' => 'cotacoes_aprovar_gerente_geral',
            'DIRETOR' => 'cotacoes_aprovar_diretor',
            'PRESIDENTE' => 'cotacoes_aprovar_presidente',
        ];
        
        $levelPermission = $levelPermissionMap[$level] ?? null;
        $hasGenericPermission = $user->hasPermission('cotacoes_aprovar');
        $hasLevelPermission = $levelPermission && $user->hasPermission($levelPermission);
        
        // Verificar se tem permissão genérica ou específica do nível
        if (!$hasGenericPermission && !$hasLevelPermission) {
            return response()->json([
                'message' => 'Você não tem permissão para aprovar este nível.',
            ], Response::HTTP_FORBIDDEN);
        }
        
        $approvalService = app(PurchaseQuoteApprovalService::class);

        // Verificar se pode aprovar (validação de níveis anteriores e perfil)
        if (!$approvalService->canApproveLevel($quote, $level, $user)) {
            return response()->json([
                'message' => 'Você não tem permissão para aprovar este nível ou há aprovações pendentes anteriores.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();

        try {
            // Processar aprovação
            $approval = $approvalService->approveLevel($quote, $level, $user, $validated['observacao'] ?? null);

            // Transições de status baseadas no nível aprovado
            $quote->refresh();
            if ($level === 'GERENTE_LOCAL') {
                // Quando Gerente Local aprova, muda para análise de gerência
                $statusGerencia = PurchaseQuoteStatus::where('slug', 'analise_gerencia')->first();
                if ($statusGerencia && $quote->current_status_slug !== 'analise_gerencia') {
                    $this->transitionStatus($quote, $statusGerencia, 'Gerente Local aprovou. Aguardando análise da Gerência Geral.');
                }
            }

            // Verificar se todas as aprovações foram concluídas
            if ($approvalService->checkAllApproved($quote)) {
                $approvedStatus = PurchaseQuoteStatus::where('slug', 'aprovado')->first();
                
                if ($approvedStatus) {
                    $this->transitionStatus($quote, $approvedStatus, 'Todas as aprovações foram concluídas.');

                    // Processar aprovação completa (criar pedidos, etc.)
                    app(PurchaseQuoteProtheusExportService::class)->prepareQuote($quote);
                    
                    try {
                        app(\App\Services\PurchaseOrderService::class)->criarPedidosPorCotacao($quote);
                    } catch (\Exception $e) {
                        Log::warning('Erro ao criar pedidos de compra', [
                            'quote_id' => $quote->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    try {
                        app(\App\Services\StockPurchaseService::class)->entrarProdutosPorCompra($quote);
                    } catch (\Exception $e) {
                        Log::warning('Erro ao entrar produtos no estoque', [
                            'quote_id' => $quote->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    try {
                        app(\App\Services\AssetPurchaseService::class)->criarAtivosPorCompra($quote);
                    } catch (\Exception $e) {
                        Log::warning('Erro ao criar ativos', [
                            'quote_id' => $quote->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Nível {$level} aprovado com sucesso.",
                'approval' => [
                    'level' => $approval->approval_level,
                    'approved_by' => $approval->approved_by_name,
                    'approved_at' => $approval->approved_at,
                ],
                'all_approved' => $approvalService->checkAllApproved($quote),
                'next_level' => $approvalService->getNextApprovalLevel($quote)?->approval_level,
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Falha ao aprovar nível', [
                'quote_id' => $quote->id,
                'level' => $level,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível aprovar o nível.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Imprimir cotação em PDF
     */
    public function imprimir(Request $request, $id)
    {
        $companyId = $request->header('company-id');
        $companyId = $companyId ? (int) $companyId : null;
        
        $quote = PurchaseQuote::with([
            'items',
            'suppliers.items',
            'buyer',
            'approvals.approver'
        ])->findOrFail($id);
        
        // Se a cotação não tiver company_id e o header tiver, atualizar
        if (!$quote->company_id && $companyId) {
            $this->updateModelWithStringTimestamps($quote, ['company_id' => $companyId]);
            $quote->refresh();
        }
        
        // Buscar empresa separadamente se necessário
        $company = null;
        if ($quote->company_id) {
            $company = Company::find($quote->company_id);
        }

        // Verificar se a cotação pertence à empresa
        // Se company_id for null, permitir acesso (cotação antiga sem company_id)
        if ($quote->company_id !== null && (int) $quote->company_id !== $companyId) {
            return response()->json([
                'message' => 'Cotação não encontrada ou não pertence à empresa.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Buscar assinaturas por perfil - usar aprovações da cotação
        $signatures = $this->getSignaturesByProfile($request, $quote->company_id, $quote);
        
        // Converter assinaturas para base64
        foreach ($signatures as $key => $signature) {
            if ($signature && isset($signature['signature_path'])) {
                $signaturePath = storage_path('app/public/' . $signature['signature_path']);
                
                if (file_exists($signaturePath)) {
                    try {
                        $imageData = file_get_contents($signaturePath);
                        if ($imageData !== false && strlen($imageData) > 0) {
                            $extension = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
                            $mimeType = 'image/png';
                            
                            switch ($extension) {
                                case 'jpg':
                                case 'jpeg':
                                    $mimeType = 'image/jpeg';
                                    break;
                                case 'png':
                                    $mimeType = 'image/png';
                                    break;
                                case 'gif':
                                    $mimeType = 'image/gif';
                                    break;
                                case 'webp':
                                    $mimeType = 'image/webp';
                                    break;
                            }
                            
                            $base64 = base64_encode($imageData);
                            $base64 = str_replace(["\r", "\n"], '', $base64);
                            $signatures[$key]['signature_base64'] = 'data:' . $mimeType . ';base64,' . $base64;
                        }
                    } catch (\Exception $e) {
                        // Se falhar, deixa sem base64
                    }
                }
            }
        }

        // Buscar referências dos produtos do estoque para itens que não têm referência
        $itemsWithReference = $quote->items->map(function ($item) use ($quote) {
            // Se o item já tem referência, não precisa buscar
            if (!empty($item->reference)) {
                return $item;
            }
            
            // Buscar referência do produto do estoque se não houver no item
            if (!empty($item->product_code) && !empty($quote->company_id)) {
                $produto = \App\Models\StockProduct::where('code', $item->product_code)
                    ->where('company_id', $quote->company_id)
                    ->first();
                
                if ($produto && !empty($produto->reference)) {
                    // Criar uma cópia do item com a referência do produto
                    $item->reference = $produto->reference;
                }
            }
            
            return $item;
        });
        
        // Preparar dados para a view
        $dados = [
            'quote' => $quote,
            'company' => $company,
            'items' => $itemsWithReference,
            'suppliers' => $quote->suppliers,
            'buyer' => $quote->buyer,
            'signatures' => $signatures,
        ];

        // Gerar PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        // Verificar o tipo solicitado via parâmetro da requisição
        // Se vier da tela de solicitação, usar template de solicitação
        // Se vier da tela de cotação, usar template comparativo (se houver fornecedores)
        $tipo = $request->get('tipo', null);
        
        if ($tipo === 'solicitacao') {
            // Sempre usar template de solicitação quando vem da tela de solicitação
            $viewTemplate = 'solicitacao';
            $paperOrientation = 'portrait';
        } elseif ($tipo === 'cotacao') {
            // Quando vem da tela de cotação, usar template comparativo se houver fornecedores
            $viewTemplate = ($quote->suppliers()->count() > 0) ? 'cotacao-comparativa' : 'solicitacao';
            $paperOrientation = ($quote->suppliers()->count() > 0) ? 'landscape' : 'portrait';
        } else {
            // Fallback: verificar se há fornecedores com valores preenchidos
            $hasSuppliersWithValues = false;
            $suppliers = $quote->suppliers;
            
            if ($suppliers->count() > 0) {
                foreach ($suppliers as $supplier) {
                    $hasItemsWithValues = $supplier->items()->where(function($query) {
                        $query->whereNotNull('unit_cost')
                              ->orWhereNotNull('final_cost')
                              ->orWhere('unit_cost', '>', 0)
                              ->orWhere('final_cost', '>', 0);
                    })->exists();
                    
                    if ($hasItemsWithValues) {
                        $hasSuppliersWithValues = true;
                        break;
                    }
                }
            }
            
            if ($hasSuppliersWithValues) {
                $viewTemplate = 'cotacao-comparativa';
                $paperOrientation = 'landscape';
            } else {
                $viewTemplate = 'solicitacao';
                $paperOrientation = 'portrait';
            }
        }
        
        $pdf = Pdf::loadView($viewTemplate, $dados);
        $pdf->getDomPDF()->setOptions($options);
        
        // Configurar tamanho do papel
        $pdf->setPaper('A4', $paperOrientation);
        
        return $pdf->stream('cotacao-' . $quote->quote_number . '.pdf');
    }

    /**
     * Buscar assinaturas por perfil (método privado)
     * Se uma cotação for fornecida, usa apenas as aprovações aprovadas
     */
    /**
     * Retorna a ordem de exibição das assinaturas (diferente da ordem de aprovação)
     */
    private function getSignatureDisplayOrder(): array
    {
        return [
            'COMPRADOR' => 1,
            'GERENTE LOCAL' => 2,
            'GERENTE GERAL' => 3,
            'ENGENHEIRO' => 4,
            'DIRETOR' => 5,
            'PRESIDENTE' => 6,
        ];
    }

    /**
     * Ordena assinaturas pela ordem de exibição
     */
    private function sortSignaturesByDisplayOrder(array $signatures): array
    {
        $displayOrder = $this->getSignatureDisplayOrder();
        
        uksort($signatures, function ($a, $b) use ($displayOrder) {
            $orderA = $displayOrder[$a] ?? 999;
            $orderB = $displayOrder[$b] ?? 999;
            return $orderA <=> $orderB;
        });
        
        return $signatures;
    }

    private function getSignaturesByProfile(Request $request, $companyId, $quote = null)
    {
        $profiles = [
            'COMPRADOR',
            'GERENTE LOCAL',
            'GERENTE GERAL',
            'ENGENHEIRO',
            'DIRETOR',
            'PRESIDENTE'
        ];

        $signatures = [];

        // Se há cotação com aprovações, usar APENAS os níveis selecionados (required = true)
        if ($quote && $quote->approvals && $quote->approvals()->exists()) {
            // Buscar APENAS os níveis de aprovação que foram SELECIONADOS (required = true) para esta cotação
            // IMPORTANTE: Carregar o relacionamento 'approver' para ter acesso à assinatura
            $requiredApprovals = $quote->approvals()
                ->with('approver')
                ->where('required', true)
                ->get();

            // Mapear nível de aprovação para nome do perfil
            $levelToProfileMap = [
                'COMPRADOR' => 'COMPRADOR',
                'GERENTE_LOCAL' => 'GERENTE LOCAL',
                'ENGENHEIRO' => 'ENGENHEIRO',
                'GERENTE_GERAL' => 'GERENTE GERAL',
                'DIRETOR' => 'DIRETOR',
                'PRESIDENTE' => 'PRESIDENTE',
            ];

            // Para cada nível selecionado, verificar se foi aprovado e adicionar assinatura
            foreach ($requiredApprovals as $approval) {
                $profileName = $levelToProfileMap[$approval->approval_level] ?? $approval->approval_level;
                
                // Se a aprovação foi realmente aprovada, adicionar assinatura
                if ($approval->approved && $approval->approved_by) {
                    // Se o relacionamento approver não estiver carregado, carregar agora
                    if (!$approval->relationLoaded('approver')) {
                        $approval->load('approver');
                    }
                    
                    $user = $approval->approver;
                    
                    // Se não encontrou o usuário pelo relacionamento, buscar diretamente
                    if (!$user && $approval->approved_by) {
                        $user = \App\Models\User::find($approval->approved_by);
                    }
                    
                    if ($user && $user->signature_path) {
                        $signatures[$profileName] = [
                            'user_id' => $user->id,
                            'user_name' => $user->nome_completo ?? $approval->approved_by_name,
                            'signature_path' => $user->signature_path,
                            'signature_url' => $request->getSchemeAndHttpHost() . '/storage/' . $user->signature_path
                        ];
                        continue;
                    }
                }
                
                // Nível selecionado mas ainda não aprovado ou sem assinatura (aguardando aprovação)
                $signatures[$profileName] = null;
            }
        } else {
            // Fallback: buscar por grupo/perfil (método antigo - apenas se não houver cotação com aprovações)
            foreach ($profiles as $profileName) {
                $user = User::whereHas('companies', function ($query) use ($companyId) {
                    $query->where('id', $companyId);
                })
                ->whereHas('groups', function ($query) use ($profileName, $companyId) {
                    $query->where(function ($groupQuery) use ($profileName) {
                        $groupQuery->where('name', 'LIKE', "%{$profileName}%")
                                   ->orWhere('name', '=', $profileName);
                    })
                    ->where('company_id', $companyId);
                })
                ->whereNotNull('signature_path')
                ->first();

                if ($user && $user->signature_path) {
                    $signatures[$profileName] = [
                        'user_id' => $user->id,
                        'user_name' => $user->nome_completo,
                        'signature_path' => $user->signature_path,
                        'signature_url' => $request->getSchemeAndHttpHost() . '/storage/' . $user->signature_path
                    ];
                } else {
                    $signatures[$profileName] = null;
                }
            }
        }

        // Ordenar assinaturas pela ordem de exibição
        return $this->sortSignaturesByDisplayOrder($signatures);
    }

    /**
     * Verifica se o usuário pode editar a cotação
     * Comprador só pode editar suas próprias cotações
     */
    private function canUserEditQuote(PurchaseQuote $quote, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Verificar se o usuário tem permissão para editar cotações
        if (!$user->hasPermission('edit_cotacoes')) {
            return false;
        }

        // Só pode editar se o status for "reprovado" ou "aguardando"
        $statusPermitidos = ['reprovado', 'aguardando'];
        if (!in_array($quote->current_status_slug, $statusPermitidos)) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se o usuário pode aprovar a cotação no momento atual
     */
    private function canUserApproveQuote(PurchaseQuote $quote, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $approvalService = app(PurchaseQuoteApprovalService::class);
        $nextLevel = $approvalService->getNextPendingLevelForUser($quote, $user);
        
        return $nextLevel !== null;
    }

    /**
     * Retorna o próximo nível de aprovação pendente para o usuário atual
     */
    private function getNextPendingLevelForCurrentUser(PurchaseQuote $quote, ?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $approvalService = app(PurchaseQuoteApprovalService::class);
        return $approvalService->getNextPendingLevelForUser($quote, $user);
    }

    /**
     * Retorna dados de acompanhamento de cotações (similar ao Excel)
     */
    public function acompanhamento(Request $request)
    {
        $companyId = $request->header('company-id');
        
        $quotes = PurchaseQuote::with([
            'statusHistory',
            'approvals',
            'buyer',
            'items',
            'orders'
        ])
        ->when($companyId, function ($query) use ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            });
        })
        ->orderByDesc('created_at')
        ->get();
        
        // Log para debug
        Log::info('Acompanhamento - Total de cotações encontradas', [
            'total' => $quotes->count(),
            'company_id' => $companyId,
        ]);

        $data = $quotes->map(function ($quote) {
            // Calcular datas importantes
            $dataSolicitacao = null;
            if ($quote->requested_at) {
                try {
                    $dataSolicitacao = \Carbon\Carbon::parse($quote->requested_at);
                } catch (\Exception $e) {
                    $dataSolicitacao = null;
                }
            }
            if (!$dataSolicitacao && $quote->created_at) {
                try {
                    $dataSolicitacao = \Carbon\Carbon::parse($quote->created_at);
                } catch (\Exception $e) {
                    $dataSolicitacao = null;
                }
            }
            $dataEncaminhamento = null; // Quando foi atribuído ao comprador
            $dataFinalizacao = null; // Quando status mudou para finalizada
            $dataAprovacaoDiretor = null; // Quando DIRETOR aprovou
            $dataLiberacaoColeta = null; // Quando foi aprovado (todos os níveis)
            $dataColeta = null; // Quando foi coletado (pode não ter)
            $dataAtendimento = null; // Quando foi atendido (pode não ter)
            
            // Buscar data de encaminhamento (quando buyer_id foi atribuído)
            if ($quote->buyer_id && $quote->statusHistory && $quote->statusHistory->isNotEmpty()) {
                $encaminhamentoHistory = $quote->statusHistory
                    ->where('status_slug', 'cotacao')
                    ->sortBy('acted_at')
                    ->first();
                $dataEncaminhamento = $encaminhamentoHistory?->acted_at ?? null;
            }
            
            // Buscar data de finalização
            if ($quote->statusHistory && $quote->statusHistory->isNotEmpty()) {
                $finalizacaoHistory = $quote->statusHistory
                    ->where('status_slug', 'finalizada')
                    ->sortBy('acted_at')
                    ->first();
                $dataFinalizacao = $finalizacaoHistory?->acted_at ?? null;
            }
            
            // Buscar data de aprovação do DIRETOR
            if ($quote->approvals && $quote->approvals->isNotEmpty()) {
                $diretorApproval = $quote->approvals
                    ->where('approval_level', 'DIRETOR')
                    ->where('approved', true)
                    ->first();
                $dataAprovacaoDiretor = $diretorApproval?->approved_at ?? null;
            }
            
            // Buscar data de liberação para coleta (quando todos os níveis foram aprovados)
            if ($quote->approvals && $quote->approvals->isNotEmpty() && $quote->isAllApproved()) {
                $ultimaAprovacao = $quote->approvals
                    ->where('approved', true)
                    ->sortByDesc('approved_at')
                    ->first();
                $dataLiberacaoColeta = $ultimaAprovacao?->approved_at ?? null;
            }
            
            // Calcular diferenças em dias
            $diasComRM = null;
            if ($dataSolicitacao) {
                try {
                    $diasComRM = now()->diffInDays($dataSolicitacao);
                } catch (\Exception $e) {
                    Log::warning('Erro ao calcular dias com RM', [
                        'quote_id' => $quote->id,
                        'data_solicitacao' => $dataSolicitacao,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $diasAtrasoInicioCotacao = null;
            if ($dataSolicitacao && $dataEncaminhamento) {
                $diasAtrasoInicioCotacao = $dataSolicitacao->diffInDays($dataEncaminhamento);
            }
            
            $tempoSolicitacao = null;
            if ($dataSolicitacao) {
                $dataFim = $dataFinalizacao ?? $dataAprovacaoDiretor ?? now();
                $tempoSolicitacao = $dataSolicitacao->diffInDays($dataFim);
            }
            
            $diasAtraso = null; // Pode ser calculado baseado em prioridade
            $prioridadeMedia = $quote->items->avg('priority_days') ?? null;
            if ($prioridadeMedia && $tempoSolicitacao !== null) {
                $diasAtraso = $tempoSolicitacao - $prioridadeMedia;
            }
            
            $diasAtrasoColeta = null;
            if ($dataLiberacaoColeta && $dataColeta) {
                $diasAtrasoColeta = $dataLiberacaoColeta->diffInDays($dataColeta);
            }
            
            $diasAtrasoColetaAtendimento = null;
            if ($dataColeta && $dataAtendimento) {
                $diasAtrasoColetaAtendimento = $dataColeta->diffInDays($dataAtendimento);
            }
            
            $diasParaEntrega = null;
            if ($dataAtendimento) {
                $diasParaEntrega = now()->diffInDays($dataAtendimento);
            }
            
            $diasFinalizacaoAprovacao = null;
            if ($dataFinalizacao && $dataAprovacaoDiretor) {
                $diasFinalizacaoAprovacao = $dataFinalizacao->diffInDays($dataAprovacaoDiretor);
            }
            
            // Descrição (observação ou primeira descrição de item)
            $descricao = $quote->observation ?? $quote->items->first()?->description ?? '-';
            
            // Número Protheus (se houver exportação)
            $numeroProtheus = $quote->protheus_export_status === 'exported' ? $quote->quote_number : null;
            
            return [
                'numero_rm' => $quote->quote_number,
                'numero_protheus' => $numeroProtheus,
                'solicitante' => $quote->requester_name,
                'prioridade' => $prioridadeMedia ? (int) $prioridadeMedia : null,
                'comprador' => $quote->buyer_name,
                'frente_obra' => $quote->work_front,
                'data_solicitacao' => $dataSolicitacao ? $dataSolicitacao->format('d/m/Y') : null,
                'data_encaminhamento' => $dataEncaminhamento ? $dataEncaminhamento->format('d/m/Y') : null,
                'data_finalizacao' => $dataFinalizacao ? $dataFinalizacao->format('d/m/Y') : null,
                'data_aprovacao_diretor' => $dataAprovacaoDiretor ? $dataAprovacaoDiretor->format('d/m/Y') : null,
                'dias_finalizacao_aprovacao' => $diasFinalizacaoAprovacao,
                'quant_dias_com_rm' => $diasComRM,
                'dias_atraso_inicio_cotacao' => $diasAtrasoInicioCotacao,
                'tempo_solicitacao' => $tempoSolicitacao,
                'dias_atraso' => $diasAtraso,
                'data_liberacao_coleta' => $dataLiberacaoColeta ? $dataLiberacaoColeta->format('d/m/Y') : null,
                'data_coleta' => $dataColeta ? $dataColeta->format('d/m/Y') : null,
                'dias_atraso_coleta' => $diasAtrasoColeta,
                'data_atendimento' => $dataAtendimento ? $dataAtendimento->format('d/m/Y') : null,
                'dias_atraso_coleta_atendimento' => $diasAtrasoColetaAtendimento,
                'quantidade_dias_entrega' => $diasParaEntrega,
                'status' => $quote->current_status_label,
                'status_slug' => $quote->current_status_slug,
                'descricao' => $descricao,
                'id' => $quote->id,
            ];
        });

        // Log para debug
        Log::info('Acompanhamento - Dados processados', [
            'total_registros' => $data->count(),
            'primeiro_registro' => $data->first(),
        ]);
        
        return response()->json([
            'data' => $data->values()->all(), // Garantir array indexado
        ], Response::HTTP_OK);
    }

    public function destroy(PurchaseQuote $quote)
    {
        // Verificar se o usuário tem permissão para deletar
        $user = auth()->user();
        if (!$user || !$user->hasPermission('cotacoes_delete')) {
            return response()->json([
                'message' => 'Você não tem permissão para deletar solicitações.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Verificar se a cotação pode ser deletada (apenas em status iniciais)
        $statusPermitidos = ['aguardando', 'reprovado'];
        if (!in_array($quote->current_status_slug, $statusPermitidos)) {
            return response()->json([
                'message' => 'Apenas solicitações com status "Aguardando" ou "Reprovado" podem ser deletadas.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            // Deletar relacionamentos em cascata
            $quote->items()->delete();
            $quote->suppliers()->delete();
            $quote->messages()->delete();
            $quote->approvals()->delete();
            $quote->statusHistory()->delete();
            
            // Deletar a cotação
            $quote->delete();

            DB::commit();

            return response()->json([
                'message' => 'Solicitação deletada com sucesso.',
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            DB::rollBack();
            
            Log::error('Falha ao deletar solicitação', [
                'quote_id' => $quote->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível deletar a solicitação.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

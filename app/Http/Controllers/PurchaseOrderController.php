<?php

namespace App\Http\Controllers;

use App\Services\PurchaseOrderService;
use App\Services\PurchaseOrderStatusService;
use App\Services\PurchaseQuote\PurchaseQuoteApprovalService;
use App\Models\PurchaseQuote;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;

class PurchaseOrderController extends Controller
{
    protected $service;

    public function __construct(PurchaseOrderService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $companyId = (int) $request->header('company-id');
        
        $filters = [
            'company_id' => $companyId,
            'purchase_quote_id' => $request->get('purchase_quote_id'),
            'order_number' => $request->get('order_number'),
            'supplier_name' => $request->get('supplier_name'),
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        // Comprador vê apenas seus pedidos; diretor/gerente veem todos
        $user = auth()->user();
        if ($user && $this->isOnlyBuyer($user, $companyId)) {
            $filters['buyer_id'] = $user->id;
        }

        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 15;
        $orders = $this->service->list($filters, $perPage);

        return response()->json([
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $order = $this->service->find($id);
        $companyId = (int) $request->header('company-id');

        if ($order->company_id != $companyId) {
            return response()->json([
                'message' => 'Pedido não encontrado ou não pertence à empresa.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Comprador só pode ver pedidos em que é o comprador da cotação
        $user = auth()->user();
        if ($user && $this->isOnlyBuyer($user, $companyId)) {
            $quoteBuyerId = $order->quote ? $order->quote->buyer_id : null;
            if ((int) $quoteBuyerId !== (int) $user->id) {
                return response()->json([
                    'message' => 'Você não tem permissão para visualizar este pedido.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return response()->json([
            'data' => $order
        ]);
    }

    /**
     * Listar pedidos por cotação
     */
    public function buscarPorCotacao(Request $request, $quoteId)
    {
        $companyId = (int) $request->header('company-id');
        
        $filters = [
            'company_id' => $companyId,
            'purchase_quote_id' => $quoteId,
        ];

        // Comprador vê apenas seus pedidos
        $user = auth()->user();
        if ($user && $this->isOnlyBuyer($user, $companyId)) {
            $filters['buyer_id'] = $user->id;
        }

        $orders = $this->service->list($filters, 100);
        
        return response()->json([
            'data' => $orders
        ]);
    }

    /**
     * Gerar pedidos de compra a partir de uma cotação aprovada
     */
    public function gerarPedidosCotacao(Request $request, $quoteId)
    {
        DB::beginTransaction();

        try {
            // Garantir que o company_id está disponível no request para o service
            $companyId = $request->header('company-id');
            if ($companyId) {
                $request->headers->set('company-id', $companyId);
            }
            
            $quote = PurchaseQuote::with('orders')->findOrFail($quoteId);
            
            // Se a cotação não tiver company_id, usar do request
            if (!$quote->company_id && $companyId) {
                $quote->company_id = (int) $companyId;
            }

            // Verificar se já tem pedidos
            if ($quote->orders()->count() > 0) {
                return response()->json([
                    'message' => 'Esta cotação já possui pedidos de compra gerados.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Verificar se está aprovada
            if ($quote->current_status_slug !== 'aprovado') {
                return response()->json([
                    'message' => 'Apenas cotações aprovadas podem ter pedidos de compra gerados.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $orders = $this->service->criarPedidosPorCotacao($quote);

            DB::commit();

            return response()->json([
                'message' => count($orders) . ' pedido(s) de compra gerado(s) com sucesso.',
                'data' => $orders,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erro ao gerar pedidos de compra.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Imprimir pedido de compra em PDF
     */
    public function imprimir(Request $request, $id)
    {
        $companyId = $request->header('company-id');
        
        $order = PurchaseOrder::with([
            'items.quoteItem',
            'company',
            'quote.buyer',
            'quote.approvals.approver',
            'quote.approvals' => function ($query) {
                $query->where('required', true);
            },
            'createdBy',
            'quoteSupplier'
        ])->findOrFail($id);
        
        // Garantir que o relacionamento approver está carregado para todas as aprovações
        if ($order->quote && $order->quote->approvals) {
            $order->quote->load(['approvals.approver']);
        }

        // Verificar se o pedido pertence à empresa
        if ($order->company_id != $companyId) {
            return response()->json([
                'message' => 'Pedido não encontrado ou não pertence à empresa.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Comprador só pode imprimir pedidos em que é o comprador da cotação
        $user = auth()->user();
        if ($user && $this->isOnlyBuyer($user, $companyId)) {
            $quoteBuyerId = $order->quote ? $order->quote->buyer_id : null;
            if ((int) $quoteBuyerId !== (int) $user->id) {
                return response()->json([
                    'message' => 'Você não tem permissão para imprimir este pedido.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Calcular totais
        $totalIten = $order->items->sum('total_price');
        $totalIPI = $order->items->sum('ipi');
        $totalICM = $order->items->sum('icms');
        $totalFRE = 0; // Frete não está no item, pode estar na cotação
        $totalDES = 0; // Despesas
        $totalSEG = 0; // Seguro
        $totalDEC = 0; // Desconto
        $valorTotal = $totalIten + $totalICM + $totalIPI + $totalSEG + $totalDES + $totalFRE - $totalDEC;

        // Recarregar a cotação para garantir que as aprovações mais recentes sejam consideradas
        if ($order->quote) {
            $order->quote->refresh();
            $order->quote->load(['approvals.approver']);
        }
        
        // Buscar assinaturas - usar aprovações da cotação se disponível
        $signatures = $this->getSignaturesByProfile($request, $companyId, $order->quote);

        // COMPRADOR: sempre usar o comprador da cotação (buyer), não quem aprovou nem quem criou o pedido
        if ($order->quote && $order->quote->buyer_id) {
            $quoteBuyer = $order->quote->relationLoaded('buyer') ? $order->quote->buyer : $order->quote->load('buyer')->buyer;
            if ($quoteBuyer) {
                $signatures['COMPRADOR'] = [
                    'user_id' => $quoteBuyer->id,
                    'user_name' => $quoteBuyer->nome_completo ?? $order->quote->buyer_name,
                    'signature_path' => $quoteBuyer->signature_path ?? null,
                    'signature_url' => $quoteBuyer->signature_path
                        ? $request->getSchemeAndHttpHost() . '/storage/' . $quoteBuyer->signature_path
                        : null,
                ];
            }
        }

        // Fallback: Se não houver comprador na cotação, usar assinatura do usuário que criou o pedido
        if ((!isset($signatures['COMPRADOR']) || !$signatures['COMPRADOR']) && $order->createdBy && $order->createdBy->signature_path) {
            $signatures['COMPRADOR'] = [
                'user_id' => $order->createdBy->id,
                'user_name' => $order->createdBy->nome_completo,
                'signature_path' => $order->createdBy->signature_path,
                'signature_url' => $request->getSchemeAndHttpHost() . '/storage/' . $order->createdBy->signature_path
            ];
        }
        
        // Converter URLs de assinaturas para base64 para o PDF
        foreach ($signatures as $key => $signature) {
            if ($signature && isset($signature['signature_path'])) {
                // Caminho correto do arquivo
                $signaturePath = storage_path('app/public/' . $signature['signature_path']);
                
                if (file_exists($signaturePath)) {
                    try {
                        $imageData = file_get_contents($signaturePath);
                        if ($imageData !== false && strlen($imageData) > 0) {
                            // Detectar tipo de imagem pela extensão
                            $extension = strtolower(pathinfo($signaturePath, PATHINFO_EXTENSION));
                            $mimeType = 'image/png'; // padrão
                            
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
                            // Remover quebras de linha do base64 para evitar problemas no PDF
                            $base64 = str_replace(["\r", "\n"], '', $base64);
                            $signatures[$key]['signature_base64'] = 'data:' . $mimeType . ';base64,' . $base64;
                        }
                    } catch (\Exception $e) {
                        // Se falhar, deixa sem base64
                    }
                }
            }
        }

        // Comprador: usar o comprador da cotação (buyer), não quem criou o pedido
        $buyer = $order->createdBy;
        if ($order->quote && $order->quote->buyer_id) {
            $quoteBuyer = $order->quote->relationLoaded('buyer') ? $order->quote->buyer : $order->quote->load('buyer')->buyer;
            if ($quoteBuyer) {
                $buyer = $quoteBuyer;
            }
        }

        // Preparar dados para a view
        $dados = [
            'order' => $order,
            'company' => $order->company,
            'items' => $order->items,
            'quote' => $order->quote,
            'buyer' => $buyer,
            'totalIten' => $totalIten,
            'totalIPI' => $totalIPI,
            'totalICM' => $totalICM,
            'totalFRE' => $totalFRE,
            'totalDES' => $totalDES,
            'totalSEG' => $totalSEG,
            'totalDEC' => $totalDEC,
            'valorTotal' => $valorTotal,
            'pageNumber' => 1,
            'totalPages' => 1,
            'signatures' => $signatures,
        ];

        // Gerar PDF com opções para suportar imagens base64
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $pdf = Pdf::loadView('pedido-compra', $dados);
        $pdf->getDomPDF()->setOptions($options);
        
        // Configurar tamanho do papel (A4)
        $pdf->setPaper('A4', 'portrait');
        
        // Opção 1: Retornar download
        // return $pdf->download('pedido-compra-' . $order->order_number . '.pdf');
        
        // Opção 2: Retornar visualização
        return $pdf->stream('pedido-compra-' . $order->order_number . '.pdf');
    }

    /**
     * Buscar assinaturas por perfil (método privado para uso interno)
     * Se uma cotação for fornecida, usa as aprovações da cotação
     */
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

        // Se há cotação com aprovações, usar APENAS os níveis selecionados (required = true) que foram aprovados
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
                        $user = User::find($approval->approved_by);
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
                
                // Nível selecionado mas ainda não aprovado ou sem assinatura (não mostra assinatura)
                if (!isset($signatures[$profileName])) {
                    $signatures[$profileName] = null;
                }
            }
            
            // Garantir que todos os perfis estejam no array (mesmo que null)
            foreach ($profiles as $profileName) {
                if (!isset($signatures[$profileName])) {
                    $signatures[$profileName] = null;
                }
            }
        } else {
            // Fallback: buscar por grupo/perfil (método antigo)
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

    /**
     * Atualizar status do pedido de compra
     */
    public function updateStatus(Request $request, $id)
    {
        // Carregar o pedido explicitamente para garantir que tem ID válido
        $order = PurchaseOrder::find($id);
        
        if (!$order) {
            Log::error('Pedido não encontrado no updateStatus', [
                'order_id' => $id,
            ]);
            return response()->json([
                'message' => 'Pedido não encontrado.',
                'error' => 'Pedido não encontrado',
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Validar que o pedido tem um ID válido
        if (!$order->id) {
            Log::error('Pedido sem ID válido no updateStatus', [
                'order_id_param' => $id,
                'order_attributes' => $order->getAttributes(),
                'order_exists' => $order->exists,
            ]);
            return response()->json([
                'message' => 'Pedido não encontrado.',
                'error' => 'Pedido sem ID válido',
            ], Response::HTTP_NOT_FOUND);
        }
        
        $validated = $request->validate([
            'status' => 'required|string',
            'justification' => 'nullable|string|max:1000',
        ]);

        $companyId = (int) $request->header('company-id');
        
        // Log inicial para debug
        Log::info('Atualizando status do pedido', [
            'order_id' => $order->id,
            'order_company_id_before' => $order->company_id,
            'request_company_id' => $companyId,
        ]);
        
        // Se o pedido não tiver company_id, atualizar com o do header
        if (!$order->company_id && $companyId) {
            Log::info('Atualizando company_id do pedido', [
                'order_id' => $order->id,
                'new_company_id' => $companyId,
            ]);
            
            try {
                $this->service->updateModelWithStringTimestamps($order, [
                    'company_id' => $companyId,
                ]);
                // O método updateModelWithStringTimestamps já faz refresh(), mas vamos garantir que o valor foi atualizado
                // Verificar diretamente no banco se necessário
                $order->refresh();
                // Forçar reload do atributo company_id
                $order->setAttribute('company_id', $companyId);
                $order->syncOriginal();
                
                Log::info('Company_id atualizado com sucesso', [
                    'order_id' => $order->id,
                    'company_id_after' => $order->company_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Erro ao atualizar company_id do pedido', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'message' => 'Erro ao atualizar pedido.',
                    'error' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        // Verificar se o pedido pertence à empresa
        $orderCompanyId = $order->company_id ? (int) $order->company_id : null;
        
        Log::info('Validação de company_id', [
            'order_id' => $order->id,
            'order_company_id' => $orderCompanyId,
            'request_company_id' => $companyId,
            'match' => $orderCompanyId === $companyId,
        ]);
        
        if ($orderCompanyId !== $companyId) {
            // Log para debug
            Log::warning('Tentativa de atualizar pedido de empresa diferente', [
                'order_id' => $order->id,
                'order_company_id' => $order->company_id,
                'order_company_id_int' => $orderCompanyId,
                'request_company_id' => $companyId,
                'types' => [
                    'order_company_id_type' => gettype($order->company_id),
                    'order_company_id_int_type' => gettype($orderCompanyId),
                    'request_company_id_type' => gettype($companyId),
                ],
            ]);
            
            return response()->json([
                'message' => 'Pedido não encontrado ou não pertence à empresa.',
                'debug' => [
                    'order_company_id' => $order->company_id,
                    'request_company_id' => $companyId,
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Verificar se o usuário é comprador
        $user = auth()->user();
        if (!$this->isBuyer($user, $companyId)) {
            return response()->json([
                'message' => 'Apenas compradores podem alterar o status do pedido.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Comprador (apenas nível COMPRADOR) só pode alterar status dos seus próprios pedidos
        if ($this->isOnlyBuyer($user, $companyId)) {
            $quoteBuyerId = $order->quote ? $order->quote->buyer_id : null;
            if ((int) $quoteBuyerId !== (int) $user->id) {
                return response()->json([
                    'message' => 'Você não tem permissão para alterar o status deste pedido.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Normalizar status do pedido: tratar vazio/null como 'pendente'
        $currentStatus = trim($order->status ?? '') ?: PurchaseOrder::STATUS_PENDENTE;
        
        // Se o status estava vazio, atualizar para 'pendente' antes de validar
        if (empty(trim($order->status ?? ''))) {
            Log::info('Status do pedido estava vazio, atualizando para pendente', [
                'order_id' => $order->id,
            ]);
            $this->service->updateModelWithStringTimestamps($order, [
                'status' => PurchaseOrder::STATUS_PENDENTE,
            ]);
            $order->refresh();
            $order->setAttribute('status', PurchaseOrder::STATUS_PENDENTE);
            $order->syncOriginal();
            $currentStatus = PurchaseOrder::STATUS_PENDENTE;
        }
        
        // Validar transição de status
        if (!$order->canTransitionTo($validated['status'])) {
            return response()->json([
                'message' => "Transição de status inválida: {$currentStatus} → {$validated['status']}",
                'debug' => [
                    'current_status' => $currentStatus,
                    'order_status_raw' => $order->status,
                    'new_status' => $validated['status'],
                    'valid_next_statuses' => $order->getValidNextStatuses(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Validar justificativa obrigatória para link_reprovado
        if ($validated['status'] === PurchaseOrder::STATUS_LINK_REPROVADO && empty($validated['justification'])) {
            return response()->json([
                'message' => 'Justificativa é obrigatória quando o LINK é reprovado.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $statusService = app(PurchaseOrderStatusService::class);
            $statusService->updateStatus(
                $order,
                $validated['status'],
                $validated['justification'] ?? null,
                $user->id
            );

            // Recarregar pedido com relacionamentos
            $order->refresh();
            $order->load(['statusHistory.changedBy', 'quote']);

            return response()->json([
                'message' => 'Status atualizado com sucesso.',
                'data' => $order,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar status do pedido', [
                'order_id' => $order->id,
                'new_status' => $validated['status'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao atualizar status do pedido.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verifica se o usuário é comprador
     */
    private function isBuyer(User $user, ?int $companyId): bool
    {
        // Verificar se o usuário tem grupo/permissão de comprador
        return $user->groups()
            ->where(function ($query) use ($companyId) {
                $query->where('name', 'LIKE', '%COMPRADOR%')
                      ->orWhere('name', 'LIKE', '%Comprador%')
                      ->orWhere('name', 'LIKE', '%comprador%');
                if ($companyId) {
                    $query->where('company_id', $companyId);
                }
            })
            ->exists();
    }

    /**
     * Verifica se o usuário é apenas comprador (tem somente nível COMPRADOR).
     * Diretor, gerente etc. têm outros níveis e podem ver todos os pedidos.
     */
    private function isOnlyBuyer(User $user, ?int $companyId): bool
    {
        $approvalService = app(PurchaseQuoteApprovalService::class);
        $userLevels = $approvalService->getUserApprovalLevels($user, $companyId ?: null);
        return count($userLevels) === 1 && in_array('COMPRADOR', $userLevels, true);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\PurchaseOrderService;
use App\Models\PurchaseQuote;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $companyId = $request->header('company-id');
        
        $filters = [
            'company_id' => $companyId,
            'purchase_quote_id' => $request->get('purchase_quote_id'),
            'order_number' => $request->get('order_number'),
            'supplier_name' => $request->get('supplier_name'),
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $perPage = (int) $request->get('per_page', 15);
        $orders = $this->service->list($filters, $perPage);

        return response()->json([
            'data' => $orders
        ]);
    }

    public function show(Request $request, $id)
    {
        $order = $this->service->find($id);
        
        return response()->json([
            'data' => $order
        ]);
    }

    /**
     * Listar pedidos por cotação
     */
    public function buscarPorCotacao(Request $request, $quoteId)
    {
        $companyId = $request->header('company-id');
        
        $filters = [
            'company_id' => $companyId,
            'purchase_quote_id' => $quoteId,
        ];

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
        
        // Fallback: Se não encontrar COMPRADOR por perfil, usar assinatura do usuário que criou o pedido
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

        // Preparar dados para a view
        $dados = [
            'order' => $order,
            'company' => $order->company,
            'items' => $order->items,
            'quote' => $order->quote,
            'buyer' => $order->createdBy,
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
}

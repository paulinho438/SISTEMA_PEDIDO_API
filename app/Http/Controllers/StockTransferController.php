<?php

namespace App\Http\Controllers;

use App\Services\StockTransferService;
use App\Http\Resources\StockTransferResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\StockTransfer;
use App\Models\Company;
use App\Models\StockLocation;
use App\Models\User;

class StockTransferController extends Controller
{
    protected $service;

    public function __construct(StockTransferService $service)
    {
        $this->service = $service;
    }

    /**
     * Listar transferências
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar transferências.',
            ], Response::HTTP_FORBIDDEN);
        }

        $transfers = $this->service->listar($request, $user);
        
        return response()->json([
            'data' => StockTransferResource::collection($transfers->items()),
            'pagination' => [
                'current_page' => $transfers->currentPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
                'last_page' => $transfers->lastPage(),
            ],
        ]);
    }

    /**
     * Buscar transferência específica
     */
    public function show($id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar transferências.',
            ], Response::HTTP_FORBIDDEN);
        }

        $transfer = StockTransfer::with(['items.product', 'items.stock', 'originLocation', 'destinationLocation', 'user'])
            ->findOrFail($id);
        
        return response()->json([
            'data' => new StockTransferResource($transfer),
        ]);
    }

    /**
     * Criar nova transferência
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar transferências.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $transfer = $this->service->criar($request, $user);
            
            return response()->json([
                'message' => 'Transferência criada com sucesso.',
                'data' => new StockTransferResource($transfer),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao criar transferência.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Marcar transferência como recebida (total ou parcial)
     */
    public function receber(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || (!$user->hasPermission('view_estoque_movimentacoes') && !$user->hasPermission('view_estoque_movimentacoes_create'))) {
            return response()->json([
                'message' => 'Você não tem permissão para receber transferências.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $dados = $request->all();
            $transfer = $this->service->receber($id, $user, $dados);
            
            return response()->json([
                'message' => 'Transferência recebida com sucesso.',
                'data' => new StockTransferResource($transfer),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao receber transferência.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Excluir transferência
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_delete')) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir transferências.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->service->excluir($id, $user);
            
            return response()->json([
                'message' => 'Transferência excluída com sucesso.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao excluir transferência.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Gerar documento PDF da transferência
     */
    public function gerarDocumento(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Não autorizado'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->hasPermission('view_estoque_movimentacoes') && !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'error' => 'Não autorizado'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $transfer = StockTransfer::with([
                'items.product',
                'items.stock',
                'originLocation',
                'destinationLocation',
                'user',
                'company'
            ])->findOrFail($id);

            $company = $transfer->company;
            $originLocation = $transfer->originLocation;
            $destinationLocation = $transfer->destinationLocation;
            $userTransfer = $transfer->user;

            $itens = $transfer->items->map(function ($item) {
                return [
                    'codigo' => $item->product->code ?? '-',
                    'referencia' => $item->product->reference ?? '-',
                    'descricao' => $item->product->description ?? '-',
                    'quantidade' => (float) $item->quantity, // Passar como float para a view formatar
                    'preco_unitario' => 0.00,
                    'preco_total' => 0.00,
                ];
            });

            $dataTransferencia = $transfer->created_at ? \Carbon\Carbon::parse($transfer->created_at)->format('d/m/Y') : date('d/m/Y');
            $horaTransferencia = $transfer->created_at ? \Carbon\Carbon::parse($transfer->created_at)->format('H:i:s') : date('H:i:s');

            $pdf = Pdf::loadView('transferencia-estoque', [
                'company' => $company,
                'user' => $userTransfer,
                'local_origem' => $originLocation,
                'local_destino' => $destinationLocation,
                'itens' => $itens,
                'observacao' => $transfer->observation,
                'data_transferencia' => $dataTransferencia,
                'hora_transferencia' => $horaTransferencia,
                'numero_documento' => $transfer->transfer_number,
                'driver_name' => $transfer->driver_name,
                'license_plate' => $transfer->license_plate,
            ]);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            
            $pdf->getDomPDF()->setOptions($options);
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download("transferencia-{$transfer->transfer_number}.pdf");
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao gerar documento: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

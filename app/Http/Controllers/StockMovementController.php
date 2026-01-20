<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockMovementResource;
use App\Services\StockMovementService;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;

class StockMovementController extends Controller
{
    protected $service;

    public function __construct(StockMovementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar movimentações de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $movements = $this->service->list($request, $user);
        return StockMovementResource::collection($movements);
    }

    public function ajuste(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar movimentações de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $movement = $this->service->ajuste($request, $user);
            
            DB::commit();
            
            return new StockMovementResource($movement);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao criar ajuste.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function entrada(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar movimentações de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $movement = $this->service->entrada($request, $user);
            
            DB::commit();
            
            return new StockMovementResource($movement);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao registrar entrada.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function transferir(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar movimentações de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $result = $this->service->transferir($request, $user);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Transferência realizada com sucesso.',
                'data' => [
                    'movement_from' => new StockMovementResource($result['movement_from']),
                    'movement_to' => new StockMovementResource($result['movement_to']),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao realizar transferência.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Transferência em lote entre locais
     */
    public function transferirLote(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar movimentações de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = \Validator::make($request->all(), [
            'local_origem_id' => 'required|exists:stock_locations,id',
            'local_destino_id' => 'required|exists:stock_locations,id|different:local_origem_id',
            'itens' => 'required|array|min:1',
            'itens.*.stock_id' => 'required|exists:stocks,id',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
            'observacao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();

        try {
            $localOrigemId = $request->input('local_origem_id');
            $localDestinoId = $request->input('local_destino_id');
            $itens = $request->input('itens');
            $observacao = $request->input('observacao');
            $companyId = $request->header('company-id');

            $resultados = [];
            $sucessos = 0;
            $falhas = 0;

            foreach ($itens as $itemData) {
                try {
                    $stock = Stock::findOrFail($itemData['stock_id']);
                    
                    // Verificar se o estoque pertence ao local de origem
                    if ($stock->stock_location_id != $localOrigemId) {
                        throw new \Exception('Estoque não pertence ao local de origem especificado.');
                    }

                    // Verificar quantidade disponível
                    if ($stock->quantity_available < $itemData['quantidade']) {
                        throw new \Exception('Quantidade disponível insuficiente.');
                    }

                    // Realizar transferência
                    $dadosTransferencia = new Request([
                        'stock_id' => $stock->id,
                        'to_location_id' => $localDestinoId,
                        'quantity' => $itemData['quantidade'],
                        'observation' => $observacao,
                    ]);
                    
                    // Adicionar o header company-id ao Request
                    $dadosTransferencia->headers->set('company-id', $companyId);

                    $resultado = $this->service->transferir($dadosTransferencia, $user);

                    $resultados[] = [
                        'stock_id' => $stock->id,
                        'product_code' => $stock->product->code ?? null,
                        'sucesso' => true,
                    ];
                    $sucessos++;
                } catch (\Exception $e) {
                    $resultados[] = [
                        'stock_id' => $itemData['stock_id'],
                        'sucesso' => false,
                        'erro' => $e->getMessage(),
                    ];
                    $falhas++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Transferência processada: {$sucessos} sucesso(s), {$falhas} falha(s).",
                'data' => [
                    'sucessos' => $sucessos,
                    'falhas' => $falhas,
                    'resultados' => $resultados,
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao processar transferência em lote.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Gerar documento PDF de transferência
     */
    public function gerarDocumentoTransferencia(Request $request)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Não autorizado',
                'error' => 'Usuário não autenticado.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Permitir gerar documento se tiver permissão de visualizar ou criar movimentações
        if (!$user->hasPermission('view_estoque_movimentacoes') && !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Não autorizado',
                'error' => 'Você não tem permissão para gerar documentos de transferência.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = \Validator::make($request->all(), [
            'local_origem_id' => 'required|exists:stock_locations,id',
            'local_destino_id' => 'required|exists:stock_locations,id',
            'itens' => 'required|array|min:1',
            'itens.*.codigo' => 'required|string',
            'itens.*.descricao' => 'required|string',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
            'observacao' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $companyId = $request->header('company-id');
            $company = Company::find($companyId);
            
            $localOrigem = StockLocation::findOrFail($request->input('local_origem_id'));
            $localDestino = StockLocation::findOrFail($request->input('local_destino_id'));
            
            $itens = $request->input('itens');
            $observacao = $request->input('observacao');
            
            // Preparar dados para a view
            $dados = [
                'company' => $company,
                'user' => $user,
                'local_origem' => $localOrigem,
                'local_destino' => $localDestino,
                'itens' => $itens,
                'observacao' => $observacao,
                'data_transferencia' => now()->format('d/m/Y'),
                'hora_transferencia' => now()->format('H:i:s'),
                'numero_documento' => now()->format('YmdHis'),
            ];

            // Gerar PDF
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', true);
            
            $pdf = Pdf::loadView('transferencia-estoque', $dados);
            $pdf->getDomPDF()->setOptions($options);
            $pdf->setPaper('A4', 'portrait');
            
            return $pdf->stream('transferencia-' . $localOrigem->code . '-' . $localDestino->code . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao gerar documento.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}


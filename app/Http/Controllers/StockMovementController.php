<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockMovementResource;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
}


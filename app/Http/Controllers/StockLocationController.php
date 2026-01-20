<?php

namespace App\Http\Controllers;

use App\Models\CustomLog;
use App\Http\Resources\StockLocationResource;
use App\Services\StockLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StockLocationController extends Controller
{
    protected $custom_log;
    protected $service;

    public function __construct(CustomLog $custom_log, StockLocationService $service)
    {
        $this->custom_log = $custom_log;
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_locais')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar locais de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->custom_log->create([
            'user_id' => $user->id,
            'content' => 'O usuário: ' . $user->nome_completo . ' acessou a tela de Locais de Estoque',
            'operation' => 'index'
        ]);

        $locations = $this->service->list($request, $user);
        return StockLocationResource::collection($locations);
    }

    public function show(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_locais')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar locais de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $location = $this->service->find($id);
        return new StockLocationResource($location);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_locais_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar locais de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $companyId = $request->header('company-id');
            $location = $this->service->create($request->all(), $companyId);
            
            DB::commit();
            
            return new StockLocationResource($location);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao criar local.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_locais_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar locais de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $location = $this->service->find($id);
            $location = $this->service->update($location, $request->all());
            
            DB::commit();
            
            return new StockLocationResource($location);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao atualizar local.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function toggleActive(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_locais_delete')) {
            return response()->json([
                'message' => 'Você não tem permissão para alterar o status de locais de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $location = $this->service->find($id);
            $location = $this->service->toggleActive($location);
            
            DB::commit();
            
            return new StockLocationResource($location);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao alterar status do local.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Listar todos os locais ativos (sem filtro de acesso) - usado para seleção de destino
     */
    public function listAllActive(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_estoque_movimentacoes_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar locais de estoque.',
            ], Response::HTTP_FORBIDDEN);
        }

        $locations = $this->service->listAllActive($request);
        return StockLocationResource::collection($locations);
    }
}


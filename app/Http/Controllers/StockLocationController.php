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
        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: ' . auth()->user()->nome_completo . ' acessou a tela de Locais de Estoque',
            'operation' => 'index'
        ]);

        $locations = $this->service->list($request, auth()->user());
        return StockLocationResource::collection($locations);
    }

    public function show(Request $request, $id)
    {
        $location = $this->service->find($id);
        return new StockLocationResource($location);
    }

    public function store(Request $request)
    {
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
}


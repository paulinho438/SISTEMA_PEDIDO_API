<?php

namespace App\Http\Controllers;

use App\Models\CustomLog;
use App\Http\Resources\AssetResource;
use App\Services\AssetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AssetController extends Controller
{
    protected $custom_log;
    protected $service;

    public function __construct(CustomLog $custom_log, AssetService $service)
    {
        $this->custom_log = $custom_log;
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $assets = $this->service->list($request);
        return AssetResource::collection($assets);
    }

    public function buscar(Request $request)
    {
        $assets = $this->service->list($request);
        return AssetResource::collection($assets);
    }

    public function show(Request $request, $id)
    {
        $asset = $this->service->find($id);
        return new AssetResource($asset);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $companyId = $request->header('company-id');
            $asset = $this->service->create($request->all(), $companyId, auth()->id());
            
            DB::commit();
            
            return new AssetResource($asset);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao criar ativo.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $asset = $this->service->find($id);
            $asset = $this->service->update($asset, $request->all(), auth()->id());
            
            DB::commit();
            
            return new AssetResource($asset);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao atualizar ativo.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function baixar(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $asset = $this->service->find($id);
            $asset = $this->service->baixar(
                $asset,
                $request->input('reason', 'Baixa de ativo'),
                $request->input('observation'),
                auth()->id()
            );
            
            DB::commit();
            
            return new AssetResource($asset);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao baixar ativo.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function transferir(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $asset = $this->service->find($id);
            $asset = $this->service->transferir(
                $asset,
                $request->input('to_branch_id'),
                $request->input('to_location_id'),
                $request->input('to_responsible_id'),
                $request->input('to_cost_center_id'),
                $request->input('observation'),
                auth()->id()
            );
            
            DB::commit();
            
            return new AssetResource($asset);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao transferir ativo.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function alterarResponsavel(Request $request, $id)
    {
        DB::beginTransaction();
        
        try {
            $asset = $this->service->find($id);
            $asset = $this->service->alterarResponsavel(
                $asset,
                $request->input('to_responsible_id'),
                $request->input('observation'),
                auth()->id()
            );
            
            DB::commit();
            
            return new AssetResource($asset);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao alterar responsável.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}


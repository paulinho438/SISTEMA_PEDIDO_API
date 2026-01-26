<?php

namespace App\Http\Controllers;

use App\Models\CustomLog;
use App\Http\Resources\AssetResource;
use App\Services\AssetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        $asset = $this->service->find($id);
        return new AssetResource($asset);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos_create')) {
            return response()->json([
                'message' => 'Você não tem permissão para criar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $companyId = $request->header('company-id');
            $asset = $this->service->create($request->all(), $companyId, $user->id);
            
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $asset = $this->service->find($id);
            $asset = $this->service->update($asset, $request->all(), $user->id);
            
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
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos_delete')) {
            return response()->json([
                'message' => 'Você não tem permissão para baixar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::beginTransaction();
        
        try {
            $asset = $this->service->find($id);
            $asset = $this->service->baixar(
                $asset,
                $request->input('reason', 'Baixa de ativo'),
                $request->input('observation'),
                $user->id
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

    /**
     * Upload de imagem do ativo
     */
    public function uploadImage(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png|max:5120', // Máximo 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Arquivo inválido',
                'error' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $asset = $this->service->find($id);

            // Deletar imagem antiga se existir
            if ($asset->image_path && Storage::disk('public')->exists($asset->image_path)) {
                Storage::disk('public')->delete($asset->image_path);
            }

            $imagePath = $this->uploadImageFile($request->file('image'), $asset->asset_number ?? 'asset_' . $asset->id);

            // Usar o service para atualizar, garantindo compatibilidade com SQL Server
            $asset = $this->service->update($asset, ['image_path' => $imagePath], $user->id);

            return response()->json([
                'message' => 'Imagem enviada com sucesso',
                'data' => [
                    'image_path' => $imagePath,
                    'image_url' => $request->getSchemeAndHttpHost() . '/storage/' . $imagePath
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao fazer upload da imagem',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remover imagem do ativo
     */
    public function removeImage(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos_edit')) {
            return response()->json([
                'message' => 'Você não tem permissão para editar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $asset = $this->service->find($id);

            // Deletar imagem se existir
            if ($asset->image_path && Storage::disk('public')->exists($asset->image_path)) {
                Storage::disk('public')->delete($asset->image_path);
            }

            // Usar o service para atualizar, garantindo compatibilidade com SQL Server
            $this->service->update($asset, ['image_path' => null], $user->id);

            return response()->json([
                'message' => 'Imagem removida com sucesso'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao remover imagem',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Gerar termo de responsabilidade de ativos
     */
    public function gerarTermoResponsabilidade(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('view_ativos')) {
            return response()->json([
                'message' => 'Você não tem permissão para visualizar ativos.',
            ], Response::HTTP_FORBIDDEN);
        }

        $responsibleId = $request->get('responsible_id');
        
        if (!$responsibleId) {
            return response()->json([
                'message' => 'ID do responsável é obrigatório.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $companyId = $request->header('company-id');
        
        if (!$companyId) {
            return response()->json([
                'message' => 'Company ID é obrigatório.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Buscar todos os ativos do responsável
        $assets = $this->service->listByResponsible($responsibleId, $companyId);
        
        if ($assets->isEmpty()) {
            return response()->json([
                'message' => 'Nenhum ativo encontrado para este responsável.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Buscar dados do responsável
        $responsible = \App\Models\AssetResponsible::find($responsibleId);
        
        if (!$responsible) {
            return response()->json([
                'message' => 'Responsável não encontrado.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Buscar dados da empresa
        $company = \App\Models\Company::find($companyId);
        
        // Preparar dados para a view
        $dados = [
            'assets' => $assets,
            'responsible' => $responsible,
            'company' => $company,
            'local_emissao' => ($company->cidade ?? '') . ($company->cidade && $company->uf ? ' - ' : '') . ($company->uf ?? ''),
            'data_emissao' => now()->format('d/m/Y'),
            'hora_emissao' => now()->format('H:i'),
        ];

        // Gerar PDF com opções para suportar imagens base64
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('termo-responsabilidade-ativos', $dados);
        $pdf->getDomPDF()->setOptions($options);
        $pdf->setPaper('A4', 'portrait');
        
        $fileName = 'termo-responsabilidade-' . preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($responsible->name ?? 'termo')) . '-' . date('Y-m-d') . '.pdf';
        
        return $pdf->download($fileName);
    }

    /**
     * Método privado para fazer upload do arquivo de imagem
     */
    private function uploadImageFile($file, $assetNumber)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = 'asset_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $assetNumber) . '_' . time() . '.' . $extension;
        $path = 'assets/' . $filename;
        
        // Criar diretório se não existir
        Storage::disk('public')->makeDirectory('assets');
        
        // Salvar arquivo
        Storage::disk('public')->put($path, file_get_contents($file));
        
        return $path;
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\AssetStandardDescription;
use App\Models\CustomLog;
use App\Http\Resources\AssetStandardDescriptionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetStandardDescriptionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Se for para o formulário de ativos, não precisa de permissão específica
        // Se for para a tela de gerenciamento, precisa de permissão
        $isManagement = $request->has('all') && $request->boolean('all');
        
        if ($isManagement && (!$user || !$user->hasPermission('view_ativos_descricoes_padrao'))) {
            return response()->json(['message' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $query = AssetStandardDescription::where('company_id', $request->header('company-id'));
        
        // Se não for para gerenciamento, mostrar apenas ativas
        if (!$isManagement) {
            $query->where('active', true);
        }
        
        $items = $query->orderBy('name')->get();

        return AssetStandardDescriptionResource::collection($items);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission('view_ativos_descricoes_padrao_create')) {
            return response()->json(['message' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $item = AssetStandardDescription::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetStandardDescriptionResource($item);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission('view_ativos_descricoes_padrao_edit')) {
            return response()->json(['message' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $item = AssetStandardDescription::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $item->update($request->all());

        return new AssetStandardDescriptionResource($item->fresh());
    }

    public function destroy($id)
    {
        $user = request()->user();
        if (!$user || !$user->hasPermission('view_ativos_descricoes_padrao_delete')) {
            return response()->json(['message' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $item = AssetStandardDescription::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


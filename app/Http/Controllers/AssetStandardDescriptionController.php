<?php

namespace App\Http\Controllers;

use App\Models\AssetStandardDescription;
use App\Models\CustomLog;
use App\Http\Resources\AssetStandardDescriptionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Filtro de busca (código, nome ou descrição)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        
        $query->orderBy('name');

        // Paginação server-side
        $perPage = (int) $request->get('per_page', 0);
        if ($perPage > 0) {
            $perPage = min($perPage, 100);
            $paginated = $query->paginate($perPage);
            $resourceArray = AssetStandardDescriptionResource::collection($paginated->items())->toArray($request);
            return response()->json(array_merge($resourceArray, [
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]));
        }
        
        $items = $query->get();
        return AssetStandardDescriptionResource::collection($items);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasPermission('view_ativos_descricoes_padrao_create')) {
            return response()->json(['message' => 'Acesso negado.'], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $companyId = $request->header('company-id');
        $item = null;

        DB::transaction(function () use ($request, $companyId, &$item) {
            $code = trim((string) $request->input('code', ''));
            if ($code === '') {
                $maxId = AssetStandardDescription::where('company_id', $companyId)->lockForUpdate()->max('id');
                $code = 'DESC-' . (($maxId ?? 0) + 1);
            }

            $item = AssetStandardDescription::create([
                'code' => $code,
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'active' => $request->boolean('active', true),
                'company_id' => $companyId,
            ]);
        });

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

        $item->update($request->only(['code', 'name', 'description', 'active']));

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


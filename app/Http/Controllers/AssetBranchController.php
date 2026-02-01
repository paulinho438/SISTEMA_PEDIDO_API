<?php

namespace App\Http\Controllers;

use App\Models\AssetBranch;
use App\Http\Resources\AssetBranchResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetBranchController extends Controller
{
    /**
     * Helper para inserir registros com timestamps como strings (compatível com SQL Server)
     */
    private function insertWithStringTimestamps($table, $data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        $values = array_values($data);
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [{$table}] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Helper para atualizar modelos com timestamps como strings (compatível com SQL Server)
     */
    private function updateModelWithStringTimestamps($model, array $data)
    {
        // Remover campos que não devem ser atualizados
        unset($data['id'], $data['created_at']);
        
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Se não há dados para atualizar além do updated_at, apenas atualizar o timestamp
        if (empty($data) || (count($data) === 1 && isset($data['updated_at']))) {
            $table = $model->getTable();
            $id = $model->getKey();
            $idColumn = $model->getKeyName();
            
            $sql = "UPDATE [{$table}] SET [updated_at] = CAST(? AS DATETIME2) WHERE [{$idColumn}] = ?";
            DB::statement($sql, [$data['updated_at'], $id]);
            $model->refresh();
            return $model;
        }
        
        // Usar DB::statement() para garantir que campos de data sejam tratados corretamente
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CAST
            if ($column === 'updated_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }
        
        $values[] = $id; // Para o WHERE
        
        $sql = "UPDATE [{$table}] SET " . implode(', ', $placeholders) . " WHERE [{$idColumn}] = ?";
        
        DB::statement($sql, $values);
        
        // Recarregar o modelo para ter os valores atualizados
        $model->refresh();
        
        return $model;
    }
    public function index(Request $request)
    {
        $companyId = $request->header('company-id');
        $query = AssetBranch::where('company_id', $companyId);

        // Aplicar filtro de ativo apenas se não for Super Administrador
        if (!$request->has('all') || !$request->boolean('all')) {
            $query->where('active', true);
        }

        // Filtro de busca (código, nome ou endereço)
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%')
                  ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy('name');

        // Paginação server-side
        $perPage = (int) $request->get('per_page', 0);
        if ($perPage > 0) {
            $perPage = min($perPage, 100);
            $paginated = $query->paginate($perPage);
            $resourceArray = AssetBranchResource::collection($paginated->items())->toArray($request);
            return response()->json(array_merge($resourceArray, [
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]));
        }

        return AssetBranchResource::collection($query->get());
    }

    public function show(Request $request, $id)
    {
        $item = AssetBranch::where('company_id', $request->header('company-id'))->findOrFail($id);
        return new AssetBranchResource($item);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $companyId = $request->header('company-id');

        $result = DB::transaction(function () use ($request, $companyId) {
            $code = trim((string) $request->input('code', ''));
            if ($code === '') {
                $maxId = AssetBranch::where('company_id', $companyId)->lockForUpdate()->max('id');
                $code = 'FIL-' . (($maxId ?? 0) + 1);
            }

            $data = [
                'code' => $code,
                'name' => $request->input('name'),
                'address' => $request->input('address'),
                'active' => $request->boolean('active', true),
                'company_id' => $companyId,
            ];

            $id = $this->insertWithStringTimestamps('asset_branches', $data);
            return ['id' => $id];
        });

        $item = AssetBranch::findOrFail($result['id']);
        return new AssetBranchResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetBranch::findOrFail($id);
        
        // Usar helper para atualizar com timestamps como strings (compatível com SQL Server)
        $this->updateModelWithStringTimestamps($item, $request->all());
        
        return new AssetBranchResource($item->fresh());
    }

    public function destroy($id)
    {
        AssetBranch::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


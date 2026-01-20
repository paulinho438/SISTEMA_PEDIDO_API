<?php

namespace App\Services;

use App\Models\StockLocation;
use App\Services\StockAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockLocationService
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
    protected $accessService;

    public function __construct(StockAccessService $accessService)
    {
        $this->accessService = $accessService;
    }

    public function list(Request $request, $user)
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 15;
        
        $companyId = $request->header('company-id');
        $query = StockLocation::where('company_id', $companyId);

        // Verificar se é Super Administrador
        $isSuperAdmin = $user->getGroupNameByEmpresaId($companyId) === 'Super Administrador';

        // Aplicar filtro de acesso
        $locationIds = $this->accessService->getAccessibleLocationIds($user, $companyId);
        if (!empty($locationIds)) {
            $query->whereIn('id', $locationIds);
        } else {
            // Se não tem acesso a nenhum local, retorna vazio
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Super Administrador pode ver todos os locais (ativos e inativos)
        // Outros usuários só veem ativos por padrão, a menos que especifique o filtro
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        } elseif (!$isSuperAdmin) {
            // Se não for Super Administrador e não especificou o filtro, mostra apenas ativos
            $query->where('active', true);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Listar todos os locais ativos (sem filtro de acesso) - usado para seleção de destino em transferências
     */
    public function listAllActive(Request $request)
    {
        $perPage = (int) $request->get('per_page', 100);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 100;
        
        $companyId = $request->header('company-id');
        $query = StockLocation::where('company_id', $companyId)
            ->where('active', true);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function find($id)
    {
        return StockLocation::findOrFail($id);
    }

    public function create(array $data, int $companyId): StockLocation
    {
        $validator = Validator::make($data, [
            'code' => 'required|string|max:50|unique:stock_locations,code,NULL,id,company_id,' . $companyId,
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $data['company_id'] = $companyId;
        
        // Usar helper para inserir com timestamps como strings (compatível com SQL Server)
        $id = $this->insertWithStringTimestamps('stock_locations', $data);
        return StockLocation::findOrFail($id);
    }

    public function update(StockLocation $location, array $data): StockLocation
    {
        $validator = Validator::make($data, [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        // Usar helper para atualizar com timestamps como strings (compatível com SQL Server)
        $this->updateModelWithStringTimestamps($location, $data);
        
        return $location->fresh();
    }

    public function toggleActive(StockLocation $location): StockLocation
    {
        // Usar helper para atualizar com timestamps como strings (compatível com SQL Server)
        $this->updateModelWithStringTimestamps($location, ['active' => !$location->active]);
        
        return $location->fresh();
    }
}


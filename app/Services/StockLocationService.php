<?php

namespace App\Services;

use App\Models\StockLocation;
use App\Services\StockAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockLocationService
{
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
        
        return StockLocation::create($data);
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

        $location->update($data);
        
        return $location->fresh();
    }

    public function toggleActive(StockLocation $location): StockLocation
    {
        $location->update(['active' => !$location->active]);
        
        return $location->fresh();
    }
}


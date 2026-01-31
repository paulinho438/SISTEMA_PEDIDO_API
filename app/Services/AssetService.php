<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AssetService
{
    /**
     * Gera número do ativo sequencial por filial
     */
    public function generateAssetNumber(int $companyId, ?int $branchId = null): string
    {
        $year = date('Y');
        $prefix = 'AT-' . $year . '-';
        
        $lastAsset = Asset::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('asset_number', 'like', $prefix . '%')
            ->orderByDesc('asset_number')
            ->first();

        if ($lastAsset) {
            $lastNumber = (int) str_replace($prefix, '', $lastAsset->asset_number);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 15;
        
        $companyId = $request->header('company-id');
        $query = Asset::where('company_id', $companyId)
            ->with(['branch', 'location', 'responsible', 'standardDescription']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhereHas('standardDescription', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->get('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('responsible_id')) {
            $query->where('responsible_id', $request->get('responsible_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->get('location_id'));
        }

        if ($request->filled('cost_center_id')) {
            $query->where('cost_center_id', $request->get('cost_center_id'));
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function listByResponsible($responsibleId, $companyId)
    {
        return Asset::where('company_id', $companyId)
            ->where('responsible_id', $responsibleId)
            ->where('status', '!=', 'baixado')
            ->with(['branch', 'location', 'responsible', 'standardDescription'])
            ->orderBy('asset_number')
            ->get();
    }

    public function find($id)
    {
        return Asset::with([
            'branch', 'location', 'responsible', 'account',
            'project', 'businessUnit', 'grouping',
            'standardDescription', 'subType1', 'subType2', 'useCondition',
            'movements', 'images'
        ])->findOrFail($id);
    }

    public function create(array $data, int $companyId, ?int $userId = null): Asset
    {
        $validator = Validator::make($data, [
            'acquisition_date' => 'required|date',
            'description' => 'required|string',
            'value_brl' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        // Gerar número do ativo se não fornecido
        if (empty($data['asset_number'])) {
            $data['asset_number'] = $this->generateAssetNumber($companyId, $data['branch_id'] ?? null);
        }

        $data['company_id'] = $companyId;
        $data['created_by'] = $userId ?? auth()->id();
        $data['status'] = $data['status'] ?? 'incluido';

        // Centro de custo do Protheus vem como código (ex: "6.19"), não como id numérico
        $data = $this->normalizeCostCenterForAsset($data);

        // SQL Server: INSERT com CAST para datas (evita conversão nvarchar → datetime fora do intervalo)
        $data['acquisition_date'] = Carbon::parse($data['acquisition_date'])->format('Y-m-d');
        $data['created_at'] = now()->format('Y-m-d H:i:s');
        $data['updated_at'] = now()->format('Y-m-d H:i:s');

        $assetId = $this->insertAssetWithCastForSqlServer($data);
        $asset = Asset::findOrFail($assetId);

        // Criar movimentação de cadastro via INSERT com CAST (SQL Server: evita conversão nvarchar → datetime)
        $movementDate = $asset->acquisition_date
            ? Carbon::parse($asset->acquisition_date)->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');
        $this->insertAssetMovementWithCastForSqlServer([
            'asset_id' => $asset->id,
            'movement_type' => 'cadastro',
            'movement_date' => $movementDate,
            'to_branch_id' => $asset->branch_id,
            'to_location_id' => $asset->location_id,
            'to_responsible_id' => $asset->responsible_id,
            'to_cost_center_id' => $asset->cost_center_id,
            'observation' => 'Cadastro inicial do ativo',
            'user_id' => $userId ?? auth()->id(),
            'reference_type' => 'ajuste_manual',
        ]);

        return $asset->fresh();
    }

    public function update(Asset $asset, array $data, ?int $userId = null): Asset
    {
        $data['updated_by'] = $userId ?? auth()->id();
        $data = $this->normalizeCostCenterForAsset($data);

        // Usar método auxiliar para garantir compatibilidade com SQL Server
        $this->updateModelWithStringTimestamps($asset, $data);

        return $asset->fresh();
    }

    /**
     * INSERT em asset_movements com CAST para colunas de data/datetime (compatível com SQL Server).
     * Público para uso por StockService e outros que precisem criar movimentações sem erro de conversão.
     */
    public function insertAssetMovementWithCastForSqlServer(array $data): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            if ($col === 'movement_date') {
                $placeholders[] = "CAST(? AS DATE)";
            } elseif (in_array($col, ['created_at', 'updated_at'], true)) {
                $placeholders[] = "CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = '?';
            }
            $values[] = $data[$col];
        }

        $colsStr = implode(', ', array_map(fn ($c) => "[{$c}]", $columns));
        $placeStr = implode(', ', $placeholders);
        $sql = "INSERT INTO [asset_movements] ({$colsStr}) VALUES ({$placeStr})";
        DB::statement($sql, $values);
    }

    /**
     * INSERT em assets com CAST para colunas de data/datetime (compatível com SQL Server).
     * Retorna o id do registro inserido.
     */
    private function insertAssetWithCastForSqlServer(array $data): int
    {
        $model = new Asset();
        $fillable = $model->getFillable();
        $data = array_intersect_key($data, array_flip($fillable));
        unset($data['id']);

        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        foreach ($columns as $col) {
            if (in_array($col, ['acquisition_date', 'document_issue_date'], true)) {
                $placeholders[] = "CAST(? AS DATE)";
            } elseif (in_array($col, ['created_at', 'updated_at'], true)) {
                $placeholders[] = "CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = '?';
            }
            $values[] = $data[$col];
        }

        $colsStr = implode(', ', array_map(fn ($c) => "[{$c}]", $columns));
        $placeStr = implode(', ', $placeholders);
        $sql = "INSERT INTO [assets] ({$colsStr}) OUTPUT INSERTED.id VALUES ({$placeStr})";
        $result = DB::select($sql, $values);
        return (int) $result[0]->id;
    }

    /**
     * Normaliza cost_center_id: se for código Protheus (string ou decimal), grava em cost_center_code e zera cost_center_id.
     */
    private function normalizeCostCenterForAsset(array $data): array
    {
        if (!array_key_exists('cost_center_id', $data)) {
            return $data;
        }
        $value = $data['cost_center_id'];
        if ($value === null || $value === '') {
            $data['cost_center_id'] = null;
            $data['cost_center_code'] = $data['cost_center_code'] ?? null;
            return $data;
        }
        $isValidInteger = is_int($value) || (is_numeric($value) && (string) (int) $value === (string) $value);
        if (!$isValidInteger) {
            $data['cost_center_code'] = (string) $value;
            $data['cost_center_id'] = null;
        } else {
            $data['cost_center_id'] = (int) $value;
            if (!isset($data['cost_center_code'])) {
                $data['cost_center_code'] = null;
            }
        }
        return $data;
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
            } elseif (in_array($column, ['acquisition_date', 'document_issue_date'])) {
                $placeholders[] = "[{$column}] = CAST(? AS DATE)";
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

    public function baixar(Asset $asset, string $reason, ?string $observation = null, ?int $userId = null): Asset
    {
        DB::beginTransaction();

        try {
            $asset->update([
                'status' => 'baixado',
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->insertAssetMovementWithCastForSqlServer([
                'asset_id' => $asset->id,
                'movement_type' => 'baixa',
                'movement_date' => Carbon::now()->format('Y-m-d'),
                'observation' => $observation ?? $reason,
                'user_id' => $userId ?? auth()->id(),
                'reference_type' => 'ajuste_manual',
            ]);

            DB::commit();

            return $asset->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function transferir(
        Asset $asset,
        ?int $toBranchId = null,
        ?int $toLocationId = null,
        ?int $toResponsibleId = null,
        ?int $toCostCenterId = null,
        ?string $observation = null,
        ?int $userId = null
    ): Asset {
        DB::beginTransaction();

        try {
            $fromBranchId = $asset->branch_id;
            $fromLocationId = $asset->location_id;
            $fromResponsibleId = $asset->responsible_id;
            $fromCostCenterId = $asset->cost_center_id;

            $asset->update([
                'branch_id' => $toBranchId ?? $asset->branch_id,
                'location_id' => $toLocationId ?? $asset->location_id,
                'responsible_id' => $toResponsibleId ?? $asset->responsible_id,
                'cost_center_id' => $toCostCenterId ?? $asset->cost_center_id,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->insertAssetMovementWithCastForSqlServer([
                'asset_id' => $asset->id,
                'movement_type' => 'transferencia',
                'movement_date' => Carbon::now()->format('Y-m-d'),
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'from_responsible_id' => $fromResponsibleId,
                'to_responsible_id' => $toResponsibleId,
                'from_cost_center_id' => $fromCostCenterId,
                'to_cost_center_id' => $toCostCenterId,
                'observation' => $observation,
                'user_id' => $userId ?? auth()->id(),
                'reference_type' => 'ajuste_manual',
            ]);

            DB::commit();

            return $asset->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function alterarResponsavel(
        Asset $asset,
        int $toResponsibleId,
        ?string $observation = null,
        ?int $userId = null
    ): Asset {
        DB::beginTransaction();

        try {
            $fromResponsibleId = $asset->responsible_id;

            $asset->update([
                'responsible_id' => $toResponsibleId,
                'updated_by' => $userId ?? auth()->id(),
            ]);

            $this->insertAssetMovementWithCastForSqlServer([
                'asset_id' => $asset->id,
                'movement_type' => 'alteracao_responsavel',
                'movement_date' => Carbon::now()->format('Y-m-d'),
                'from_responsible_id' => $fromResponsibleId,
                'to_responsible_id' => $toResponsibleId,
                'observation' => $observation ?? 'Alteração de responsável',
                'user_id' => $userId ?? auth()->id(),
                'reference_type' => 'ajuste_manual',
            ]);

            DB::commit();

            return $asset->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}


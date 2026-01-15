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
            ->with(['branch', 'location', 'responsible']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
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

        $asset = Asset::create($data);

        // Criar movimentação de cadastro
        AssetMovement::create([
            'asset_id' => $asset->id,
            'movement_type' => 'cadastro',
            'movement_date' => $asset->acquisition_date ?? Carbon::now()->toDateString(),
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
        $asset->update([
            ...$data,
            'updated_by' => $userId ?? auth()->id(),
        ]);

        return $asset->fresh();
    }

    public function baixar(Asset $asset, string $reason, ?string $observation = null, ?int $userId = null): Asset
    {
        DB::beginTransaction();

        try {
            $asset->update([
                'status' => 'baixado',
                'updated_by' => $userId ?? auth()->id(),
            ]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'movement_type' => 'baixa',
                'movement_date' => Carbon::now()->toDateString(),
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

            AssetMovement::create([
                'asset_id' => $asset->id,
                'movement_type' => 'transferencia',
                'movement_date' => Carbon::now()->toDateString(),
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

            AssetMovement::create([
                'asset_id' => $asset->id,
                'movement_type' => 'alteracao_responsavel',
                'movement_date' => Carbon::now()->toDateString(),
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


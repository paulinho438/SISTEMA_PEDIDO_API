<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\StockAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class StockMovementService
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
        $query = StockMovement::where('stock_movements.company_id', $companyId)
            ->with(['product', 'location', 'user']);

        // Aplicar filtro de acesso
        $this->accessService->applyLocationFilter($query, $user, $companyId, 'stock_location_id');

        if ($request->filled('product_id')) {
            $query->where('stock_product_id', $request->get('product_id'));
        }

        if ($request->filled('location_id')) {
            $query->where('stock_location_id', $request->get('location_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->get('movement_type'));
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->get('reference_type'));
        }

        if ($request->filled('date_from')) {
            $query->where('movement_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('movement_date', '<=', $request->get('date_to'));
        }

        return $query->orderByDesc('movement_date')->orderByDesc('id')->paginate($perPage);
    }

    public function ajuste(Request $request, $user): StockMovement
    {
        $validator = Validator::make($request->all(), [
            'stock_id' => 'required|exists:stocks,id',
            'movement_type' => 'required|in:entrada,saida',
            'quantity' => 'required|numeric|min:0.0001',
            'cost' => 'nullable|numeric|min:0',
            'observation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $stock = Stock::findOrFail($request->input('stock_id'));
        $companyId = $request->header('company-id');

        // Verificar acesso
        if (!$this->accessService->canAccessLocation($user, $stock->stock_location_id, $companyId)) {
            throw new \Exception('Acesso negado a este local.');
        }

        DB::beginTransaction();

        try {
            $movementType = $request->input('movement_type');
            $quantity = $request->input('quantity');
            $cost = $request->input('cost');
            
            if ($movementType === 'saida') {
                $quantity = -$quantity;
                if ($stock->quantity_available + $quantity < 0) {
                    throw new \Exception('Quantidade disponível insuficiente.');
                }
            }

            $quantityBefore = $stock->quantity_available;
            $quantityAfter = $stock->quantity_available + $quantity;

            $stock->update([
                'quantity_available' => $quantityAfter,
                'quantity_total' => $stock->quantity_total + $quantity,
                'last_movement_at' => Carbon::now(),
            ]);

            $movement = StockMovement::create([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => $request->input('movement_type'),
                'quantity' => abs($quantity),
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => 'ajuste_manual',
                'cost' => $cost,
                'total_cost' => $cost ? $cost * abs($quantity) : null,
                'observation' => $request->input('observation'),
                'user_id' => $user->id,
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return $movement->load(['product', 'location', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Entrada manual de produto no estoque
     * Cria ou atualiza o estoque do produto no local especificado
     */
    public function entrada(Request $request, $user): StockMovement
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:stock_products,id',
            'location_id' => 'required|exists:stock_locations,id',
            'quantity' => 'required|numeric|min:0.0001',
            'cost' => 'nullable|numeric|min:0',
            'observation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $companyId = $request->header('company-id');

        // Verificar acesso ao local de destino
        if (!$this->accessService->canAccessLocation($user, $request->input('location_id'), $companyId)) {
            throw new \Exception('Acesso negado a este local.');
        }

        DB::beginTransaction();

        try {
            $productId = $request->input('product_id');
            $locationId = $request->input('location_id');
            $quantity = $request->input('quantity');
            $cost = $request->input('cost');

            // Buscar ou criar estoque
            $stock = Stock::firstOrCreate(
                [
                    'stock_product_id' => $productId,
                    'stock_location_id' => $locationId,
                    'company_id' => $companyId,
                ],
                [
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_total' => 0,
                ]
            );

            $quantityBefore = $stock->quantity_available;
            $quantityAfter = $stock->quantity_available + $quantity;

            $stock->update([
                'quantity_available' => $quantityAfter,
                'quantity_total' => $stock->quantity_total + $quantity,
                'last_movement_at' => Carbon::now(),
            ]);

            $movement = StockMovement::create([
                'stock_id' => $stock->id,
                'stock_product_id' => $productId,
                'stock_location_id' => $locationId,
                'movement_type' => 'entrada',
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'reference_type' => 'ajuste_manual',
                'cost' => $cost,
                'total_cost' => $cost ? $cost * $quantity : null,
                'observation' => $request->input('observation'),
                'user_id' => $user->id,
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return $movement->load(['product', 'location', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Transferência de estoque entre locais
     */
    public function transferir(Request $request, $user): array
    {
        $validator = Validator::make($request->all(), [
            'stock_id' => 'required|exists:stocks,id',
            'to_location_id' => 'required|exists:stock_locations,id',
            'quantity' => 'required|numeric|min:0.0001',
            'observation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $stock = Stock::findOrFail($request->input('stock_id'));
        $companyId = $request->header('company-id');
        $toLocationId = $request->input('to_location_id');
        $quantity = $request->input('quantity');

        // Verificar acesso ao local de origem
        if (!$this->accessService->canAccessLocation($user, $stock->stock_location_id, $companyId)) {
            throw new \Exception('Acesso negado ao local de origem.');
        }

        // Verificar acesso ao local de destino
        if (!$this->accessService->canAccessLocation($user, $toLocationId, $companyId)) {
            throw new \Exception('Acesso negado ao local de destino.');
        }

        // Verificar se é o mesmo local
        if ($stock->stock_location_id == $toLocationId) {
            throw new \Exception('O local de origem e destino devem ser diferentes.');
        }

        // Verificar quantidade disponível
        if ($stock->quantity_available < $quantity) {
            throw new \Exception('Quantidade disponível insuficiente para transferência.');
        }

        DB::beginTransaction();

        try {
            // Atualizar estoque de origem (saída)
            $quantityBeforeFrom = $stock->quantity_available;
            $quantityAfterFrom = $stock->quantity_available - $quantity;

            $stock->update([
                'quantity_available' => $quantityAfterFrom,
                'quantity_total' => $stock->quantity_total - $quantity,
                'last_movement_at' => Carbon::now(),
            ]);

            // Criar movimentação de saída
            $movementFrom = StockMovement::create([
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'transferencia',
                'quantity' => -$quantity,
                'quantity_before' => $quantityBeforeFrom,
                'quantity_after' => $quantityAfterFrom,
                'reference_type' => 'transferencia',
                'observation' => $request->input('observation') . ' (Transferência: Origem)',
                'user_id' => $user->id,
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            // Buscar ou criar estoque de destino
            $stockTo = Stock::firstOrCreate(
                [
                    'stock_product_id' => $stock->stock_product_id,
                    'stock_location_id' => $toLocationId,
                    'company_id' => $companyId,
                ],
                [
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_total' => 0,
                ]
            );

            // Atualizar estoque de destino (entrada)
            $quantityBeforeTo = $stockTo->quantity_available;
            $quantityAfterTo = $stockTo->quantity_available + $quantity;

            $stockTo->update([
                'quantity_available' => $quantityAfterTo,
                'quantity_total' => $stockTo->quantity_total + $quantity,
                'last_movement_at' => Carbon::now(),
            ]);

            // Criar movimentação de entrada
            $movementTo = StockMovement::create([
                'stock_id' => $stockTo->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $toLocationId,
                'movement_type' => 'transferencia',
                'quantity' => $quantity,
                'quantity_before' => $quantityBeforeTo,
                'quantity_after' => $quantityAfterTo,
                'reference_type' => 'transferencia',
                'observation' => $request->input('observation') . ' (Transferência: Destino)',
                'user_id' => $user->id,
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);

            DB::commit();

            return [
                'movement_from' => $movementFrom->load(['product', 'location', 'user']),
                'movement_to' => $movementTo->load(['product', 'location', 'user']),
                'stock_from' => $stock->fresh(['product', 'location']),
                'stock_to' => $stockTo->fresh(['product', 'location']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}


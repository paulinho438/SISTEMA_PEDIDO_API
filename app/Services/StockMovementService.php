<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockLocation;
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
        
        $companyId = (int) $request->header('company-id');
        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }
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
        $companyId = (int) $request->header('company-id');

        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

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

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_available' => $quantityAfter,
                'quantity_total' => $stock->quantity_total + $quantity,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $movementId = $this->insertWithStringTimestamps('stock_movements', [
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
            
            $movement = StockMovement::find($movementId);

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

        $companyId = (int) $request->header('company-id');

        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

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
            $stock = Stock::where('stock_product_id', $productId)
                ->where('stock_location_id', $locationId)
                ->where('company_id', $companyId)
                ->first();
            
            if (!$stock) {
                $stockId = $this->insertStockWithStringTimestamps([
                    'stock_product_id' => $productId,
                    'stock_location_id' => $locationId,
                    'company_id' => $companyId,
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_total' => 0,
                ]);
                $stock = Stock::find($stockId);
            }

            $quantityBefore = $stock->quantity_available;
            $quantityAfter = $stock->quantity_available + $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_available' => $quantityAfter,
                'quantity_total' => $stock->quantity_total + $quantity,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $movementId = $this->insertWithStringTimestamps('stock_movements', [
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
            
            $movement = StockMovement::find($movementId);

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
        $companyId = (int) $request->header('company-id');
        $toLocationId = $request->input('to_location_id');
        $quantity = $request->input('quantity');

        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

        // Verificar acesso ao local de origem (obrigatório)
        if (!$this->accessService->canAccessLocation($user, $stock->stock_location_id, $companyId)) {
            throw new \Exception('Acesso negado ao local de origem.');
        }

        // Verificar se o local de destino existe e está ativo (não precisa de acesso específico)
        // NOTA: Removida a validação de acesso ao destino - usuário pode transferir para qualquer local ativo
        $toLocation = StockLocation::where('id', $toLocationId)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->first();
            
        if (!$toLocation) {
            throw new \Exception('Local de destino inválido ou inativo.');
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
            // Gerar número de transferência único
            $transferNumber = $this->generateTransferNumber($companyId);

            // Atualizar estoque de origem (saída)
            $quantityBeforeFrom = $stock->quantity_available;
            $quantityAfterFrom = $stock->quantity_available - $quantity;

            $this->updateModelWithStringTimestamps($stock, [
                'quantity_available' => $quantityAfterFrom,
                'quantity_total' => $stock->quantity_total - $quantity,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Criar movimentação de saída
            $movementFromId = $this->insertWithStringTimestamps('stock_movements', [
                'stock_id' => $stock->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $stock->stock_location_id,
                'movement_type' => 'transferencia',
                'quantity' => -$quantity,
                'quantity_before' => $quantityBeforeFrom,
                'quantity_after' => $quantityAfterFrom,
                'reference_type' => 'transferencia',
                'transfer_number' => $transferNumber,
                'observation' => $request->input('observation') . ' (Transferência: Origem)',
                'user_id' => $user->id,
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);
            
            $movementFrom = StockMovement::find($movementFromId);

            // Buscar ou criar estoque de destino
            $stockTo = Stock::where('stock_product_id', $stock->stock_product_id)
                ->where('stock_location_id', $toLocationId)
                ->where('company_id', $companyId)
                ->first();
            
            if (!$stockTo) {
                $stockToId = $this->insertStockWithStringTimestamps([
                    'stock_product_id' => $stock->stock_product_id,
                    'stock_location_id' => $toLocationId,
                    'company_id' => $companyId,
                    'quantity_available' => 0,
                    'quantity_reserved' => 0,
                    'quantity_total' => 0,
                ]);
                $stockTo = Stock::find($stockToId);
            }

            // Atualizar estoque de destino (entrada)
            $quantityBeforeTo = $stockTo->quantity_available;
            $quantityAfterTo = $stockTo->quantity_available + $quantity;

            $this->updateModelWithStringTimestamps($stockTo, [
                'quantity_available' => $quantityAfterTo,
                'quantity_total' => $stockTo->quantity_total + $quantity,
                'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Criar movimentação de entrada
            $movementToId = $this->insertWithStringTimestamps('stock_movements', [
                'stock_id' => $stockTo->id,
                'stock_product_id' => $stock->stock_product_id,
                'stock_location_id' => $toLocationId,
                'movement_type' => 'transferencia',
                'quantity' => $quantity,
                'quantity_before' => $quantityBeforeTo,
                'quantity_after' => $quantityAfterTo,
                'reference_type' => 'transferencia',
                'transfer_number' => $transferNumber,
                'observation' => $request->input('observation') . ' (Transferência: Destino)',
                'user_id' => $user->id,
                'company_id' => $companyId,
                'movement_date' => Carbon::now()->toDateString(),
            ]);
            
            $movementTo = StockMovement::find($movementToId);

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
            if ($column === 'updated_at' || $column === 'last_movement_at') {
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

    /**
     * Helper para inserir registros com timestamps como strings (compatível com SQL Server)
     */
    private function insertWithStringTimestamps($table, $data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            // Campos de data precisam de CAST
            if ($column === 'movement_date') {
                $placeholders[] = "CAST(? AS DATE)";
            } else {
                $placeholders[] = "?";
            }
            $values[] = $data[$column];
        }
        
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
     * Helper para inserir estoque com timestamps como strings (compatível com SQL Server)
     */
    private function insertStockWithStringTimestamps($data)
    {
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];
        
        foreach ($columns as $column) {
            $placeholders[] = "?";
            $values[] = $data[$column];
        }
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [stocks] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }

    /**
     * Gerar número único de transferência manual
     * Verifica tanto stock_transfers quanto stock_movements para garantir sequência única
     */
    private function generateTransferNumber($companyId)
    {
        $year = date('Y');
        
        // Buscar último número de transferência em lote (stock_transfers)
        $lastBatchTransfer = DB::table('stock_transfers')
            ->where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->whereNotNull('transfer_number')
            ->orderBy('id', 'desc')
            ->first();
        
        // Buscar último número de transferência manual (stock_movements)
        $lastManualTransfer = DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->where('movement_type', 'transferencia')
            ->whereNotNull('transfer_number')
            ->orderBy('id', 'desc')
            ->first();
        
        // Determinar o maior número de sequência
        $maxSequence = 0;
        
        if ($lastBatchTransfer && $lastBatchTransfer->transfer_number) {
            // Extrair sequência do formato TRF-YYYY-000001 ou TRANS-X
            $batchNumber = $lastBatchTransfer->transfer_number;
            if (preg_match('/-(\d+)$/', $batchNumber, $matches)) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }
        
        if ($lastManualTransfer && $lastManualTransfer->transfer_number) {
            // Extrair sequência do formato TRANS-X
            $manualNumber = $lastManualTransfer->transfer_number;
            if (preg_match('/-(\d+)$/', $manualNumber, $matches)) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }
        
        // Gerar próximo número no formato TRANS-X
        $nextSequence = $maxSequence + 1;
        
        return sprintf('TRANS-%d', $nextSequence);
    }
}


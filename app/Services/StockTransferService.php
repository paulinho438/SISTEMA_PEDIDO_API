<?php

namespace App\Services;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Stock;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockTransferService
{
    /**
     * Criar nova transferência com status pendente
     */
    public function criar(Request $request, User $user): StockTransfer
    {
        $companyId = (int) $request->header('company-id');
        
        if (!$companyId) {
            throw new \Exception('Company ID é obrigatório.');
        }

        $validated = $request->validate([
            'local_origem_id' => 'required|exists:stock_locations,id',
            'local_destino_id' => 'required|exists:stock_locations,id|different:local_origem_id',
            'driver_name' => 'nullable|string|max:255',
            'license_plate' => 'nullable|string|max:20',
            'observation' => 'nullable|string',
            'observacao' => 'nullable|string', // Aceitar também em português
            'itens' => 'required|array|min:1',
            'itens.*.stock_id' => 'required|exists:stocks,id',
            'itens.*.quantidade' => 'required|numeric|min:0.0001',
        ]);
        
        // Mapear observacao para observation se necessário
        if (isset($validated['observacao']) && !isset($validated['observation'])) {
            $validated['observation'] = $validated['observacao'];
        }

        DB::beginTransaction();

        try {
            // Verificar locais
            $originLocation = StockLocation::where('id', $validated['local_origem_id'])
                ->where('company_id', $companyId)
                ->where('active', true)
                ->firstOrFail();

            $destinationLocation = StockLocation::where('id', $validated['local_destino_id'])
                ->where('company_id', $companyId)
                ->where('active', true)
                ->firstOrFail();

            // Criar transferência
            $transferData = [
                'origin_location_id' => $validated['local_origem_id'],
                'destination_location_id' => $validated['local_destino_id'],
                'driver_name' => $validated['driver_name'] ?? null,
                'license_plate' => $validated['license_plate'] ?? null,
                'status' => 'pendente',
                'observation' => $validated['observation'] ?? null,
                'user_id' => $user->id,
                'company_id' => $companyId,
            ];

            $transferId = $this->insertTransferWithStringTimestamps($transferData);
            $transfer = StockTransfer::findOrFail($transferId);

            // Criar itens da transferência e fazer saída do estoque de origem
            foreach ($validated['itens'] as $itemData) {
                $stock = Stock::findOrFail($itemData['stock_id']);
                
                // Verificar se pertence ao local de origem
                if ($stock->stock_location_id != $validated['local_origem_id']) {
                    throw new \Exception("Estoque ID {$stock->id} não pertence ao local de origem.");
                }

                // Verificar quantidade disponível
                if ($stock->quantity_available < $itemData['quantidade']) {
                    throw new \Exception("Quantidade disponível insuficiente para o estoque ID {$stock->id}.");
                }

                $quantityBefore = $stock->quantity_available;

                // Criar item da transferência
                $itemDataInsert = [
                    'stock_transfer_id' => $transfer->id,
                    'stock_id' => $stock->id,
                    'stock_product_id' => $stock->stock_product_id,
                    'quantity' => $itemData['quantidade'],
                    'quantity_available_before' => $quantityBefore,
                ];

                $this->insertTransferItemWithStringTimestamps($itemDataInsert);

                // Atualizar estoque de origem (reduzir disponível)
                $this->updateStockWithStringTimestamps($stock, [
                    'quantity_available' => $quantityBefore - $itemData['quantidade'],
                    'quantity_total' => $stock->quantity_total - $itemData['quantidade'],
                    'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
            }

            DB::commit();

            return $transfer->load(['items.stock', 'items.product', 'originLocation', 'destinationLocation', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Marcar transferência como recebida (total ou parcial)
     */
    public function receber(int $transferId, User $user, array $dados = []): StockTransfer
    {
        $transfer = StockTransfer::with(['items.stock', 'items.product'])->findOrFail($transferId);

        if ($transfer->status === 'recebido') {
            throw new \Exception('Esta transferência já foi totalmente recebida.');
        }

        $tipo = $dados['tipo'] ?? 'total';
        $itensRecebidos = $dados['itens'] ?? [];

        DB::beginTransaction();

        try {
            $statusFinal = 'recebido';
            $quantidadeTotalRecebida = 0;
            $quantidadeTotalOriginal = $transfer->items->sum('quantity');

            if ($tipo === 'parcial') {
                if (empty($itensRecebidos)) {
                    throw new \Exception('É necessário informar os itens recebidos para recebimento parcial.');
                }

                // Validar itens recebidos
                foreach ($itensRecebidos as $itemRecebido) {
                    $item = $transfer->items->firstWhere('id', $itemRecebido['item_id']);
                    if (!$item) {
                        throw new \Exception("Item ID {$itemRecebido['item_id']} não encontrado na transferência.");
                    }

                    $quantidadeRecebida = (float) ($itemRecebido['quantidade_recebida'] ?? 0);
                    if ($quantidadeRecebida <= 0) {
                        throw new \Exception("Quantidade recebida deve ser maior que zero para o item ID {$item->id}.");
                    }
                    if ($quantidadeRecebida > $item->quantity) {
                        throw new \Exception("Quantidade recebida ({$quantidadeRecebida}) não pode ser maior que a quantidade original ({$item->quantity}) para o item ID {$item->id}.");
                    }

                    $quantidadeTotalRecebida += $quantidadeRecebida;
                }

                // Se recebeu tudo, marcar como recebido total
                if ($quantidadeTotalRecebida >= $quantidadeTotalOriginal) {
                    $statusFinal = 'recebido';
                } else {
                    $statusFinal = 'recebido_parcial';
                }

                // Processar apenas os itens recebidos
                foreach ($itensRecebidos as $itemRecebido) {
                    $item = $transfer->items->firstWhere('id', $itemRecebido['item_id']);
                    $quantidadeRecebida = (float) $itemRecebido['quantidade_recebida'];

                    // Buscar ou criar estoque no local de destino
                    $destinationStock = Stock::where('stock_product_id', $item->stock_product_id)
                        ->where('stock_location_id', $transfer->destination_location_id)
                        ->where('company_id', $transfer->company_id)
                        ->first();

                    if ($destinationStock) {
                        // Atualizar estoque existente
                        $this->updateStockWithStringTimestamps($destinationStock, [
                            'quantity_available' => $destinationStock->quantity_available + $quantidadeRecebida,
                            'quantity_total' => $destinationStock->quantity_total + $quantidadeRecebida,
                            'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                    } else {
                        // Criar novo estoque
                        $this->insertStockWithStringTimestamps([
                            'stock_product_id' => $item->stock_product_id,
                            'stock_location_id' => $transfer->destination_location_id,
                            'company_id' => $transfer->company_id,
                            'quantity_available' => $quantidadeRecebida,
                            'quantity_reserved' => 0,
                            'quantity_total' => $quantidadeRecebida,
                        ]);
                    }
                }
            } else {
                // Recebimento total - processar todos os itens
                foreach ($transfer->items as $item) {
                    // Buscar ou criar estoque no local de destino
                    $destinationStock = Stock::where('stock_product_id', $item->stock_product_id)
                        ->where('stock_location_id', $transfer->destination_location_id)
                        ->where('company_id', $transfer->company_id)
                        ->first();

                    if ($destinationStock) {
                        // Atualizar estoque existente
                        $this->updateStockWithStringTimestamps($destinationStock, [
                            'quantity_available' => $destinationStock->quantity_available + $item->quantity,
                            'quantity_total' => $destinationStock->quantity_total + $item->quantity,
                            'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        ]);
                    } else {
                        // Criar novo estoque
                        $this->insertStockWithStringTimestamps([
                            'stock_product_id' => $item->stock_product_id,
                            'stock_location_id' => $transfer->destination_location_id,
                            'company_id' => $transfer->company_id,
                            'quantity_available' => $item->quantity,
                            'quantity_reserved' => 0,
                            'quantity_total' => $item->quantity,
                        ]);
                    }
                }
            }

            // Atualizar status
            $this->updateTransferWithStringTimestamps($transfer, [
                'status' => $statusFinal,
            ]);

            DB::commit();

            return $transfer->fresh(['items.stock', 'items.product', 'originLocation', 'destinationLocation', 'user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Excluir transferência e devolver materiais ao estoque de origem
     */
    public function excluir(int $transferId, User $user): void
    {
        $transfer = StockTransfer::with(['items.stock'])->findOrFail($transferId);

        if ($transfer->status === 'recebido') {
            throw new \Exception('Não é possível excluir uma transferência já recebida.');
        }

        DB::beginTransaction();

        try {
            // Devolver materiais ao estoque de origem
            foreach ($transfer->items as $item) {
                $stock = Stock::find($item->stock_id);
                
                if ($stock) {
                    $this->updateStockWithStringTimestamps($stock, [
                        'quantity_available' => $stock->quantity_available + $item->quantity,
                        'quantity_total' => $stock->quantity_total + $item->quantity,
                        'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Excluir transferência (cascade vai excluir os itens)
            DB::statement("DELETE FROM [stock_transfers] WHERE [id] = ?", [$transferId]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Listar transferências
     */
    public function listar(Request $request, User $user)
    {
        $companyId = (int) $request->header('company-id');
        
        $query = StockTransfer::with(['originLocation', 'destinationLocation', 'user', 'items.product'])
            ->where('company_id', $companyId);

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('local_origem_id')) {
            $query->where('origin_location_id', $request->input('local_origem_id'));
        }

        if ($request->has('local_destino_id')) {
            $query->where('destination_location_id', $request->input('local_destino_id'));
        }

        // Ordenar por mais recente
        $query->orderBy('created_at', 'desc');

        return $query->paginate($request->input('per_page', 15));
    }

    // Helpers para SQL Server
    private function insertTransferWithStringTimestamps(array $data): int
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = "INSERT INTO [stock_transfers] ([" . implode('], [', $columns) . "]) OUTPUT INSERTED.[id] VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = DB::select($sql, $values);
        return $result[0]->id;
    }

    private function updateTransferWithStringTimestamps(StockTransfer $transfer, array $data): void
    {
        $data['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];

        foreach ($columns as $column) {
            if ($column === 'updated_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }

        $values[] = $transfer->id;

        $sql = "UPDATE [stock_transfers] SET " . implode(', ', $placeholders) . " WHERE [id] = ?";
        DB::statement($sql, $values);
    }

    private function insertTransferItemWithStringTimestamps(array $data): int
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);

        $sql = "INSERT INTO [stock_transfer_items] ([" . implode('], [', $columns) . "]) OUTPUT INSERTED.[id] VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = DB::select($sql, $values);
        return $result[0]->id;
    }

    private function updateStockWithStringTimestamps(Stock $stock, array $data): void
    {
        $data['updated_at'] = Carbon::now()->format('Y-m-d H:i:s');
        
        $columns = array_keys($data);
        $placeholders = [];
        $values = [];

        foreach ($columns as $column) {
            if ($column === 'updated_at' || $column === 'last_movement_at') {
                $placeholders[] = "[{$column}] = CAST(? AS DATETIME2)";
            } else {
                $placeholders[] = "[{$column}] = ?";
            }
            $values[] = $data[$column];
        }

        $values[] = $stock->id;

        $sql = "UPDATE [stocks] SET " . implode(', ', $placeholders) . " WHERE [id] = ?";
        DB::statement($sql, $values);
    }

    private function insertStockWithStringTimestamps(array $data): int
    {
        $createdAt = Carbon::now()->format('Y-m-d H:i:s');
        $updatedAt = Carbon::now()->format('Y-m-d H:i:s');
        
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
        
        $sql = "INSERT INTO [stocks] (" . implode(', ', $columnsBracketed) . ") OUTPUT INSERTED.[id] VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = DB::select($sql, $values);
        return $result[0]->id;
    }
}


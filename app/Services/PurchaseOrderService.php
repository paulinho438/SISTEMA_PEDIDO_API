<?php

namespace App\Services;

use App\Models\PurchaseQuote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseQuoteItem;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseOrderService
{
    /**
     * Helper para atualizar modelos com timestamps como strings (compatível com SQL Server)
     */
    private function updateModelWithStringTimestamps($model, array $data)
    {
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Usar DB::statement() para garantir que updated_at seja string
        $table = $model->getTable();
        $id = $model->getKey();
        $idColumn = $model->getKeyName();
        
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
        $placeholders = array_fill(0, count($data), '?');
        $values = array_values($data);
        
        // Adicionar campos de data com CAST
        $columns[] = 'created_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $createdAt;
        
        $columns[] = 'updated_at';
        $placeholders[] = "CAST(? AS DATETIME2)";
        $values[] = $updatedAt;
        
        // Usar colchetes nos nomes das colunas para evitar problemas com palavras reservadas (ex: order)
        $columnsBracketed = array_map(fn($col) => "[{$col}]", $columns);
        
        $sql = "INSERT INTO [{$table}] (" . implode(', ', $columnsBracketed) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        DB::statement($sql, $values);
        
        // Retornar o ID do último registro inserido
        return DB::getPdo()->lastInsertId();
    }
    /**
     * Criar pedidos de compra a partir de cotação aprovada
     * Um pedido por fornecedor selecionado
     */
    public function criarPedidosPorCotacao(PurchaseQuote $quote): array
    {
        DB::beginTransaction();

        try {
            // Garantir que company_id está disponível
            // Recarregar a cotação para garantir que tem todos os campos
            $quote->refresh();
            $companyId = $quote->company_id;
            
            // Se não tiver company_id na cotação, tentar pegar do request
            if (!$companyId) {
                $request = request();
                $companyId = $request->header('company-id');
                if ($companyId) {
                    // Atualizar a cotação com o company_id usando helper para timestamps como strings
                    $this->updateModelWithStringTimestamps($quote, [
                        'company_id' => (int) $companyId,
                    ]);
                }
            }
            
            // Se ainda não tiver, lançar erro
            if (!$companyId) {
                throw new \Exception('Company ID não encontrado. A cotação deve ter uma empresa associada.');
            }
            
            // Converter para inteiro
            $companyId = (int) $companyId;
            
            $userId = auth()->id();
            $orders = [];

            // Agrupar itens por fornecedor selecionado
            $itemsBySupplier = $quote->items()
                ->whereNotNull('selected_supplier_id')
                ->with(['selectedSupplier', 'selectedSupplier.items'])
                ->get()
                ->groupBy('selected_supplier_id');

            if ($itemsBySupplier->isEmpty()) {
                throw new \Exception('Nenhum fornecedor foi selecionado na cotação.');
            }

            foreach ($itemsBySupplier as $supplierId => $items) {
                $supplier = $items->first()->selectedSupplier;

                if (!$supplier) {
                    continue;
                }

                // Criar pedido de compra usando helper para timestamps como strings
                $orderId = $this->insertWithStringTimestamps('purchase_orders', [
                    'order_number' => PurchaseOrder::generateNextNumber(),
                    'order_date' => Carbon::now()->toDateString(),
                    'expected_delivery_date' => null, // Pode ser calculado baseado em priority_days
                    'purchase_quote_id' => $quote->id,
                    'purchase_quote_supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->supplier_name,
                    'supplier_document' => $supplier->supplier_document,
                    'supplier_code' => $supplier->supplier_code,
                    'vendor_name' => $supplier->vendor_name,
                    'vendor_phone' => $supplier->vendor_phone,
                    'vendor_email' => $supplier->vendor_email,
                    'proposal_number' => $supplier->proposal_number,
                    'total_amount' => 0, // Será calculado
                    'status' => 'pendente',
                    'observation' => $quote->observation, // Observação da cotação (que vem da solicitação)
                    'company_id' => $companyId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                
                $order = PurchaseOrder::findOrFail($orderId);

                $totalAmount = 0;

                // Adicionar itens ao pedido
                foreach ($items as $quoteItem) {
                    // Buscar item do fornecedor para pegar valores de impostos
                    $supplierItem = $supplier->items()
                        ->where('purchase_quote_item_id', $quoteItem->id)
                        ->first();

                    $unitPrice = $quoteItem->selected_unit_cost ?? ($supplierItem->unit_cost ?? 0);
                    $quantity = $quoteItem->quantity ?? 1;
                    $totalPrice = $unitPrice * $quantity;
                    $finalCost = $supplierItem->final_cost ?? $totalPrice;

                    // Criar item do pedido usando helper para timestamps como strings
                    $this->insertWithStringTimestamps('purchase_order_items', [
                        'purchase_order_id' => $order->id,
                        'purchase_quote_id' => $quote->id,
                        'purchase_quote_item_id' => $quoteItem->id,
                        'purchase_quote_supplier_item_id' => $supplierItem?->id,
                        'product_code' => $quoteItem->product_code,
                        'product_description' => $quoteItem->description,
                        'quantity' => $quantity,
                        'unit' => $quoteItem->unit ?? 'UN',
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'ipi' => $supplierItem->ipi ?? null,
                        'icms' => $supplierItem->icms ?? null,
                        'final_cost' => $finalCost,
                        'observation' => $quoteItem->application ?? null,
                    ]);

                    $totalAmount += $finalCost;
                }

                // Atualizar total do pedido usando helper para timestamps como strings
                $this->updateModelWithStringTimestamps($order, ['total_amount' => $totalAmount]);

                $orders[] = $order->load(['items', 'quoteSupplier']);
            }

            DB::commit();

            return $orders;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Buscar pedido com relacionamentos
     */
    public function find(int $id): PurchaseOrder
    {
        return PurchaseOrder::with([
            'quote',
            'quoteSupplier',
            'items.quoteItem',
            'items.quoteSupplierItem',
            'company',
            'createdBy',
            'updatedBy'
        ])->findOrFail($id);
    }

    /**
     * Listar pedidos
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = PurchaseOrder::with(['quote', 'quoteSupplier', 'company']);

        if (isset($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (isset($filters['purchase_quote_id'])) {
            $query->where('purchase_quote_id', $filters['purchase_quote_id']);
        }

        if (isset($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
        }

        if (isset($filters['supplier_name'])) {
            $query->where('supplier_name', 'like', '%' . $filters['supplier_name'] . '%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('order_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('order_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('order_date')->paginate($perPage);
    }
}


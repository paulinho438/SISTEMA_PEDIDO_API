<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseQuote;
use App\Models\StockProduct;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PurchaseInvoiceService
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
     * Criar nota fiscal e dar entrada no estoque
     */
    public function criarNotaFiscalEDarEntrada(array $data, int $companyId, ?int $userId = null): PurchaseInvoice
    {
        $validator = Validator::make($data, [
            'invoice_number' => 'required|string|max:50',
            'invoice_series' => 'nullable|string|max:10',
            'invoice_date' => 'required|date',
            'received_date' => 'nullable|date',
            'purchase_quote_id' => 'nullable|exists:purchase_quotes,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_document' => 'nullable|string|max:20',
            'total_amount' => 'nullable|numeric|min:0',
            'observation' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_code' => 'nullable|string|max:100',
            'items.*.product_description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.purchase_quote_item_id' => 'nullable|exists:purchase_quote_items,id',
            'items.*.stock_location_id' => 'required|exists:stock_locations,id',
            'items.*.observation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $userId = $userId ?? auth()->id();

        DB::beginTransaction();

        try {
            // Se tiver pedido de compra, buscar dados do pedido
            $purchaseOrder = null;
            if (!empty($data['purchase_order_id'])) {
                $purchaseOrder = PurchaseOrder::with(['items.quoteItem', 'quoteSupplier'])
                    ->where('id', $data['purchase_order_id'])
                    ->where('company_id', $companyId)
                    ->first();
                if (!$purchaseOrder) {
                    throw new \Exception('Pedido de compra não encontrado ou não pertence à empresa.');
                }
                // Verificar se o pedido pertence à empresa
                if ((int) $purchaseOrder->company_id !== $companyId) {
                    throw new \Exception('Pedido de compra não pertence à empresa.');
                }
                // Preencher dados do fornecedor do pedido se não informados
                if (empty($data['supplier_name'])) {
                    $data['supplier_name'] = $purchaseOrder->supplier_name;
                }
                if (empty($data['supplier_document'])) {
                    $data['supplier_document'] = $purchaseOrder->supplier_document;
                }
                if (empty($data['purchase_quote_id'])) {
                    $data['purchase_quote_id'] = $purchaseOrder->purchase_quote_id;
                }
            }

            // 1. Criar nota fiscal usando helper para timestamps como strings
            $invoiceId = $this->insertWithStringTimestamps('purchase_invoices', [
                'invoice_number' => $data['invoice_number'],
                'invoice_series' => $data['invoice_series'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'received_date' => $data['received_date'] ?? $data['invoice_date'],
                'purchase_quote_id' => $data['purchase_quote_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_document' => $data['supplier_document'] ?? null,
                'total_amount' => $data['total_amount'] ?? 0,
                'observation' => $data['observation'] ?? null,
                'company_id' => $companyId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            
            $invoice = PurchaseInvoice::findOrFail($invoiceId);

            $purchaseQuote = null;
            if (!empty($data['purchase_quote_id'])) {
                $purchaseQuote = PurchaseQuote::where('id', $data['purchase_quote_id'])
                    ->where('company_id', $companyId)
                    ->first();
                if (!$purchaseQuote) {
                    throw new \Exception('Cotação não encontrada ou não pertence à empresa.');
                }
            }

            // 2. Processar cada item da nota fiscal
            foreach ($data['items'] as $itemData) {
                $this->processarItemNotaFiscal($invoice, $itemData, $companyId, $userId, $purchaseQuote, $purchaseOrder);
            }

            // 3. Recalcular total da nota fiscal usando helper para timestamps como strings
            $totalAmount = PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)
                ->sum('total_price');
            
            $this->updateModelWithStringTimestamps($invoice, ['total_amount' => $totalAmount]);

            // 4. Se tiver pedido associado, atualizar quantidades recebidas e status
            if ($purchaseOrder) {
                $this->atualizarQuantidadesRecebidasPedido($invoice, $purchaseOrder);
            }

            DB::commit();

            return $invoice->load(['items.product', 'items.quoteItem', 'quote']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Processar item da nota fiscal e dar entrada no estoque
     */
    protected function processarItemNotaFiscal(
        PurchaseInvoice $invoice,
        array $itemData,
        int $companyId,
        int $userId,
        ?PurchaseQuote $quote,
        ?PurchaseOrder $order = null
    ): void {
        // 1. Verificar/criar produto no estoque
        $product = StockProduct::firstOrCreate(
            [
                'code' => $itemData['product_code'] ?? $itemData['product_description'],
                'company_id' => $companyId,
            ],
            [
                'description' => $itemData['product_description'],
                'reference' => null,
                'unit' => $itemData['unit'] ?? 'UN',
                'active' => true,
            ]
        );

        // 2. Criar item da nota fiscal
        // Se tiver pedido, buscar purchase_order_item_id
        $purchaseOrderItemId = null;
        if ($order) {
            // Tentar encontrar pelo purchase_order_item_id se vier no itemData (vinculado ao pedido)
            if (!empty($itemData['purchase_order_item_id'])) {
                $orderItem = $order->items()->find($itemData['purchase_order_item_id']);
                $purchaseOrderItemId = $orderItem?->id;
            }
            
            // Se não encontrou, tentar pelo purchase_quote_item_id
            if (!$purchaseOrderItemId && !empty($itemData['purchase_quote_item_id'])) {
                $orderItem = $order->items()
                    ->where('purchase_quote_item_id', $itemData['purchase_quote_item_id'])
                    ->first();
                $purchaseOrderItemId = $orderItem?->id;
            }
        }

        // Criar item da nota fiscal usando helper para timestamps como strings
        $invoiceItemId = $this->insertWithStringTimestamps('purchase_invoice_items', [
            'purchase_invoice_id' => $invoice->id,
            'purchase_quote_id' => $quote?->id,
            'purchase_quote_item_id' => $itemData['purchase_quote_item_id'] ?? null,
            'purchase_order_item_id' => $purchaseOrderItemId,
            'stock_product_id' => $product->id,
            'stock_location_id' => $itemData['stock_location_id'],
            'product_code' => $itemData['product_code'] ?? null,
            'product_description' => $itemData['product_description'],
            'quantity' => $itemData['quantity'],
            'unit' => $itemData['unit'] ?? 'UN',
            'unit_price' => $itemData['unit_price'],
            'total_price' => $itemData['total_price'],
            'observation' => $itemData['observation'] ?? null,
        ]);
        
        $invoiceItem = PurchaseInvoiceItem::findOrFail($invoiceItemId);

        // 3. Verificar local de estoque
        $location = StockLocation::findOrFail($itemData['stock_location_id']);

        // Converter ambos para inteiro para comparação correta
        $locationCompanyId = (int) ($location->company_id ?? 0);
        $requestCompanyId = (int) ($companyId ?? 0);

        if (!$requestCompanyId) {
            throw new \Exception('Company ID não informado na requisição.');
        }

        if (!$locationCompanyId) {
            throw new \Exception('Local de estoque não possui empresa associada.');
        }

        if ($locationCompanyId !== $requestCompanyId) {
            throw new \Exception("Local de estoque não pertence à empresa. Local ID {$location->id} pertence à empresa ID: {$locationCompanyId}, mas a requisição é para empresa ID: {$requestCompanyId}.");
        }

        // 4. Criar/atualizar estoque
        $stock = Stock::firstOrCreate(
            [
                'stock_product_id' => $product->id,
                'stock_location_id' => $location->id,
                'company_id' => $companyId,
            ],
            [
                'quantity_available' => 0,
                'quantity_reserved' => 0,
                'quantity_total' => 0,
            ]
        );

        // 5. Atualizar estoque
        $quantity = (float) $itemData['quantity'];
        $quantityBefore = $stock->quantity_available;
        $quantityAfter = $quantityBefore + $quantity;

        // Usar helper para atualizar com timestamps como strings (compatível com SQL Server)
        $this->updateModelWithStringTimestamps($stock, [
            'quantity_available' => $quantityAfter,
            'quantity_total' => $stock->quantity_total + $quantity,
            'last_movement_at' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        // 6. Criar movimentação de entrada associada à nota fiscal usando helper para timestamps como strings
        $this->insertWithStringTimestamps('stock_movements', [
            'stock_id' => $stock->id,
            'stock_product_id' => $product->id,
            'stock_location_id' => $location->id,
            'movement_type' => 'entrada',
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => 'compra',
            'reference_id' => $quote?->id,
            'reference_number' => $invoice->invoice_number,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'cost' => $itemData['unit_price'],
            'total_cost' => $itemData['total_price'],
            'observation' => "Entrada por nota fiscal {$invoice->invoice_number}" . ($invoice->invoice_series ? "/{$invoice->invoice_series}" : ''),
            'user_id' => $userId,
            'company_id' => $companyId,
            'movement_date' => $invoice->received_date ?? $invoice->invoice_date,
        ]);
    }

    /**
     * Buscar pedido de compra com itens para pré-preencher nota fiscal
     */
    public function buscarPedidoParaNota(int $orderId, int $companyId): PurchaseOrder
    {
        $order = PurchaseOrder::with([
            'items.quoteItem',
            'items.quoteSupplierItem',
            'quote',
            'quoteSupplier'
        ])
            ->where('id', $orderId)
            ->where('company_id', $companyId)
            ->first();
        
        if (!$order) {
            throw new \Exception("Pedido de compra ID {$orderId} não encontrado ou não pertence à empresa ID {$companyId}.");
        }

        return $order;
    }

    /**
     * Buscar nota fiscal com relacionamentos
     */
    public function find(int $id): PurchaseInvoice
    {
        return PurchaseInvoice::with([
            'items.product',
            'items.quoteItem',
            'quote',
            'order',
            'company',
            'createdBy',
            'updatedBy'
        ])->findOrFail($id);
    }

    /**
     * Listar notas fiscais
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        $query = PurchaseInvoice::with(['quote', 'company']);

        if (isset($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (isset($filters['purchase_quote_id'])) {
            $query->where('purchase_quote_id', $filters['purchase_quote_id']);
        }

        if (isset($filters['invoice_number'])) {
            $query->where('invoice_number', 'like', '%' . $filters['invoice_number'] . '%');
        }

        if (isset($filters['supplier_name'])) {
            $query->where('supplier_name', 'like', '%' . $filters['supplier_name'] . '%');
        }

        if (isset($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('invoice_date')->paginate($perPage);
    }

    /**
     * Atualizar quantidades recebidas do pedido e status baseado nas NFs
     */
    protected function atualizarQuantidadesRecebidasPedido(PurchaseInvoice $invoice, PurchaseOrder $order): void
    {
        // Para cada item da nota fiscal, incrementar quantity_received no pedido
        foreach ($invoice->items as $invoiceItem) {
            if ($invoiceItem->purchase_order_item_id) {
                $orderItem = PurchaseOrderItem::find($invoiceItem->purchase_order_item_id);
                
                if ($orderItem) {
                    $quantityReceived = (float) ($orderItem->quantity_received ?? 0);
                    $quantityReceived += (float) $invoiceItem->quantity;
                    
                    // Não pode receber mais do que foi pedido
                    $quantityReceived = min($quantityReceived, (float) $orderItem->quantity);
                    
                    // Usar helper para atualizar com timestamps como strings
                    $this->updateModelWithStringTimestamps($orderItem, ['quantity_received' => $quantityReceived]);
                }
            }
        }

        // Atualizar status do pedido baseado nas quantidades recebidas
        $this->atualizarStatusPedido($order);
    }

    /**
     * Atualizar status do pedido baseado nas quantidades recebidas
     * pendente -> parcial -> recebido
     */
    protected function atualizarStatusPedido(PurchaseOrder $order): void
    {
        $order->load('items');
        
        $totalItems = $order->items->count();
        $itemsRecebidos = 0;
        $itemsParcialmenteRecebidos = 0;

        foreach ($order->items as $item) {
            $quantityReceived = (float) ($item->quantity_received ?? 0);
            $quantity = (float) $item->quantity;

            if ($quantityReceived >= $quantity) {
                $itemsRecebidos++;
            } elseif ($quantityReceived > 0) {
                $itemsParcialmenteRecebidos++;
            }
        }

        $newStatus = 'pendente';
        
        if ($itemsRecebidos === $totalItems && $totalItems > 0) {
            // Todos os itens foram recebidos completamente
            $newStatus = 'recebido';
        } elseif ($itemsRecebidos > 0 || $itemsParcialmenteRecebidos > 0) {
            // Alguns itens foram recebidos (totalmente ou parcialmente)
            $newStatus = 'parcial';
        }

        if ($order->status !== $newStatus) {
            // Usar helper para atualizar com timestamps como strings
            $this->updateModelWithStringTimestamps($order, ['status' => $newStatus]);
        }
    }
}


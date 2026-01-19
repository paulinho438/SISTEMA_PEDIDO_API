<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderStatusHistory;
use App\Models\PurchaseQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderStatusService
{
    protected $orderService;

    public function __construct(PurchaseOrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Atualiza o status do pedido de compra
     */
    public function updateStatus(PurchaseOrder $order, string $newStatus, ?string $justification = null, ?int $userId = null): void
    {
        // Validar que o pedido tem um ID válido ANTES de qualquer operação
        $orderId = $order->id;
        if (!$orderId) {
            Log::error('PurchaseOrderStatusService: Pedido sem ID válido', [
                'order_exists' => $order->exists ?? 'N/A',
                'order_attributes' => $order->getAttributes(),
                'order_key' => $order->getKey(),
                'order_key_name' => $order->getKeyName(),
            ]);
            throw new \InvalidArgumentException("Pedido sem ID válido. O pedido precisa ter um ID antes de atualizar o status.");
        }
        
        // Validar transição
        if (!$order->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Transição de status inválida: {$order->status} → {$newStatus}");
        }

        DB::beginTransaction();

        try {
            // Preservar o ID e status antes de qualquer atualização
            $oldStatus = $order->status ?? '';
            
            // Atualizar status do pedido usando método que trata timestamps corretamente
            $updateData = ['status' => $newStatus];
            if ($userId) {
                $updateData['updated_by'] = $userId;
            }
            $this->orderService->updateModelWithStringTimestamps($order, $updateData);
            
            // Garantir que o ID ainda está presente após o refresh
            if (!$order->id) {
                $order->setAttribute('id', $orderId);
            }

            // Registrar histórico usando o ID preservado
            $this->createHistory($order, $orderId, $oldStatus, $newStatus, $justification, $userId);

            // Se link_reprovado, atualizar cotação
            if ($newStatus === PurchaseOrder::STATUS_LINK_REPROVADO) {
                $this->handleLinkReprovado($order, $justification);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar status do pedido', [
                'order_id' => $order->id,
                'old_status' => $oldStatus ?? null,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cria registro no histórico de status
     */
    protected function createHistory(PurchaseOrder $order, int $orderId, string $oldStatus, string $newStatus, ?string $justification, ?int $userId): void
    {
        // Usar o ID passado explicitamente para garantir que não seja NULL
        $finalOrderId = $orderId ?: $order->id;
        
        if (!$finalOrderId) {
            Log::error('Tentativa de criar histórico sem ID do pedido', [
                'order_id_from_param' => $orderId,
                'order_id_from_model' => $order->id,
                'order_attributes' => $order->getAttributes(),
            ]);
            throw new \InvalidArgumentException("Não é possível criar histórico sem ID do pedido");
        }
        
        $changedBy = $userId ?? auth()->id();
        if (!$changedBy) {
            Log::warning('Tentativa de criar histórico sem ID do usuário', [
                'user_id_from_param' => $userId,
                'auth_id' => auth()->id(),
            ]);
            throw new \InvalidArgumentException("Não é possível criar histórico sem ID do usuário");
        }
        
        // Usar inserção direta com SQL para garantir compatibilidade com SQL Server
        $createdAt = now()->format('Y-m-d H:i:s');
        $updatedAt = now()->format('Y-m-d H:i:s');
        
        DB::table('purchase_order_status_histories')->insert([
            'purchase_order_id' => $finalOrderId,
            'old_status' => $oldStatus ?? '',
            'new_status' => $newStatus,
            'justification' => $justification,
            'changed_by' => $changedBy,
            'created_at' => DB::raw("CAST('{$createdAt}' AS DATETIME2)"),
            'updated_at' => DB::raw("CAST('{$updatedAt}' AS DATETIME2)"),
        ]);
    }

    /**
     * Trata o caso de link_reprovado: retorna cotação para compra_em_andamento
     */
    protected function handleLinkReprovado(PurchaseOrder $order, ?string $justification): void
    {
        $quote = $order->quote;
        
        if (!$quote) {
            Log::warning('Pedido sem cotação associada ao reprovar link', [
                'order_id' => $order->id,
            ]);
            return;
        }

        // Buscar status "compra_em_andamento"
        $statusCompraEmAndamento = \App\Models\PurchaseQuoteStatus::where('slug', 'compra_em_andamento')->first();
        
        if (!$statusCompraEmAndamento) {
            Log::warning('Status compra_em_andamento não encontrado', [
                'order_id' => $order->id,
                'quote_id' => $quote->id,
            ]);
            return;
        }

        // Atualizar status da cotação usando método que trata timestamps corretamente
        $this->updateQuoteWithStringTimestamps($quote, [
            'current_status_id' => $statusCompraEmAndamento->id,
            'current_status_slug' => $statusCompraEmAndamento->slug,
            'current_status_label' => $statusCompraEmAndamento->label,
        ]);

        // Criar mensagem na cotação com a justificativa
        if ($justification) {
            $this->createQuoteMessage($quote, $justification, $order);
        }
    }

    /**
     * Cria mensagem na cotação
     */
    protected function createQuoteMessage(PurchaseQuote $quote, string $message, PurchaseOrder $order): void
    {
        try {
            // Usar inserção com timestamps como strings (compatível com SQL Server)
            $createdAt = now()->format('Y-m-d H:i:s');
            $updatedAt = now()->format('Y-m-d H:i:s');
            
            DB::table('purchase_quote_messages')->insert([
                'purchase_quote_id' => $quote->id,
                'user_id' => auth()->id() ?? 1, // Fallback para sistema se não houver usuário autenticado
                'type' => 'link_reprovado',
                'message' => "LINK Reprovado - Pedido {$order->order_number}: {$message}",
                'created_at' => DB::raw("CAST('{$createdAt}' AS DATETIME2)"),
                'updated_at' => DB::raw("CAST('{$updatedAt}' AS DATETIME2)"),
            ]);
        } catch (\Exception $e) {
            Log::warning('Erro ao criar mensagem na cotação', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper para atualizar PurchaseQuote com timestamps como strings (compatível com SQL Server)
     */
    protected function updateQuoteWithStringTimestamps(PurchaseQuote $quote, array $data): void
    {
        // Adicionar updated_at como string
        $data['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Usar DB::statement() para garantir que updated_at seja string
        $table = $quote->getTable();
        $id = $quote->getKey();
        $idColumn = $quote->getKeyName();
        
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
        $quote->refresh();
    }
}


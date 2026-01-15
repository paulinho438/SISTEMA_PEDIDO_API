<?php

namespace App\Services\PurchaseQuote;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteItem;
use App\Models\PurchaseQuoteSupplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\PurchaseQuote\PurchaseQuoteProductDefaultsService;

class PurchaseQuoteProtheusExportService
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
            // Campos de data precisam de CAST
            if ($column === 'updated_at' || $column === 'protheus_exported_at' || $column === 'approved_at') {
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
     * Helper para salvar modelo com timestamps como strings (compatível com SQL Server)
     */
    private function saveModelWithStringTimestamps($model)
    {
        // Se não há mudanças, não precisa salvar
        if (!$model->isDirty()) {
            return $model;
        }
        
        // Pegar todos os atributos modificados
        $dirty = $model->getDirty();
        
        // Adicionar updated_at
        $dirty['updated_at'] = now()->format('Y-m-d H:i:s');
        
        // Se for novo modelo, adicionar created_at também
        if (!$model->exists) {
            $dirty['created_at'] = now()->format('Y-m-d H:i:s');
        }
        
        // Usar updateModelWithStringTimestamps para atualizar
        if ($model->exists) {
            return $this->updateModelWithStringTimestamps($model, $dirty);
        } else {
            // Para novos modelos, usar insert
            $table = $model->getTable();
            $columns = array_keys($dirty);
            $placeholders = [];
            $values = [];
            
            foreach ($columns as $column) {
                if ($column === 'created_at' || $column === 'updated_at' || $column === 'protheus_exported_at' || $column === 'approved_at') {
                    $placeholders[] = "CAST(? AS DATETIME2)";
                } else {
                    $placeholders[] = "?";
                }
                $values[] = $dirty[$column];
            }
            
            $sql = "INSERT INTO [{$table}] (" . implode(', ', array_map(fn($c) => "[{$c}]", $columns)) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            DB::statement($sql, $values);
            
            // Buscar o ID do último registro inserido
            $id = DB::getPdo()->lastInsertId();
            $model->setAttribute($model->getKeyName(), $id);
            $model->exists = true;
            $model->refresh();
            
            return $model;
        }
    }
    /**
     * Prepara a cotação aprovada para exportação ao Protheus.
     */
    public function prepareQuote(PurchaseQuote $quote): void
    {
        $quote->loadMissing(['items', 'suppliers.items']);

        app(PurchaseQuoteProductDefaultsService::class)->apply($quote);

        $selectedSupplierIds = $quote->items
            ->pluck('selected_supplier_id')
            ->filter()
            ->unique();

        foreach ($quote->suppliers as $supplier) {
            if (!$selectedSupplierIds->contains($supplier->id)) {
                $this->markSupplierAsNotRequired($supplier);
                continue;
            }

            $items = $quote->items->where('selected_supplier_id', $supplier->id);
            $previousStatus = $supplier->protheus_export_status ?? 'idle';
            [$status, $message] = $this->synchronizeSupplierData($quote, $supplier, $items);

            $supplier->fill([
                'protheus_export_status' => $status,
                'protheus_last_error' => $message,
            ]);

            if ($status === 'pending') {
                if ($previousStatus !== 'pending') {
                    $supplier->protheus_export_attempts = 0;
                }
                $supplier->protheus_last_error = null;
                $supplier->protheus_exported_at = null;
                $supplier->protheus_order_number = null;
            } elseif ($status === 'blocked') {
                $supplier->protheus_exported_at = null;
                $supplier->protheus_order_number = null;
            }

            if ($supplier->isDirty()) {
                $this->saveModelWithStringTimestamps($supplier);
            }
        }

        $this->refreshQuoteExportSummary($quote);
    }

    /**
     * Marca o fornecedor como não aplicável para exportação.
     */
    public function markSupplierAsNotRequired(PurchaseQuoteSupplier $supplier): void
    {
        $supplier->fill([
            'protheus_export_status' => 'not_required',
            'protheus_last_error' => null,
            'protheus_export_attempts' => 0,
            'protheus_exported_at' => null,
            'protheus_order_number' => null,
        ]);

        if ($supplier->isDirty()) {
            $this->saveModelWithStringTimestamps($supplier);
        }
    }

    /**
     * Marca o fornecedor como em processamento para evitar múltiplas integrações simultâneas.
     */
    public function markSupplierInProcess(PurchaseQuoteSupplier $supplier): void
    {
        $supplier->refresh();
        $previousStatus = $supplier->protheus_export_status ?? 'idle';

        $supplier->fill([
            'protheus_export_status' => 'processing',
            'protheus_last_error' => null,
        ]);

        if ($previousStatus !== 'processing') {
            $supplier->protheus_export_attempts = ($supplier->protheus_export_attempts ?? 0) + 1;
        }

        if ($supplier->isDirty()) {
            $this->saveModelWithStringTimestamps($supplier);
        }

        if ($supplier->relationLoaded('quote')) {
            $quote = $supplier->quote;
        } else {
            $quote = $supplier->quote()->first();
        }

        if ($quote) {
            $this->refreshQuoteExportSummary($quote);
        }
    }

    /**
     * Registra a conclusão com sucesso da exportação.
     */
    public function markSupplierSuccess(PurchaseQuoteSupplier $supplier, string $orderNumber, ?string $message = null): void
    {
        $supplier->refresh();

        $supplier->fill([
            'protheus_export_status' => 'exported',
            'protheus_order_number' => $orderNumber,
            'protheus_exported_at' => now(),
            'protheus_last_error' => $message,
        ]);

        if ($supplier->isDirty()) {
            $this->saveModelWithStringTimestamps($supplier);
        }

        $quote = $supplier->relationLoaded('quote') ? $supplier->quote : $supplier->quote()->first();

        if ($quote) {
            $this->refreshQuoteExportSummary($quote);
        }
    }

    /**
     * Registra falha durante a exportação.
     */
    public function markSupplierFailure(PurchaseQuoteSupplier $supplier, string $errorMessage): void
    {
        $supplier->refresh();

        $supplier->fill([
            'protheus_export_status' => 'error',
            'protheus_last_error' => $errorMessage,
        ]);

        if ($supplier->isDirty()) {
            $this->saveModelWithStringTimestamps($supplier);
        }

        $quote = $supplier->relationLoaded('quote') ? $supplier->quote : $supplier->quote()->first();

        if ($quote) {
            $this->refreshQuoteExportSummary($quote);
        }
    }

    /**
     * Reinicia os status de exportação quando a cotação volta para ajustes.
     */
    public function resetQuote(PurchaseQuote $quote): void
    {
        $quote->loadMissing('suppliers');

        foreach ($quote->suppliers as $supplier) {
            $supplier->fill([
                'protheus_export_status' => 'idle',
                'protheus_exported_at' => null,
                'protheus_order_number' => null,
                'protheus_export_attempts' => 0,
                'protheus_last_error' => null,
            ]);

            if ($supplier->isDirty()) {
                $this->saveModelWithStringTimestamps($supplier);
            }
        }

        $quote->fill([
            'protheus_export_status' => 'idle',
            'protheus_exported_at' => null,
        ]);

        if ($quote->isDirty()) {
            $this->saveModelWithStringTimestamps($quote);
        }
    }

    /**
     * Recalcula o resumo de status de exportação da cotação.
     */
    public function refreshQuoteExportSummary(PurchaseQuote $quote): void
    {
        $quote->loadMissing('suppliers');

        $statuses = $quote->suppliers
            ->pluck('protheus_export_status')
            ->filter()
            ->map(fn ($status) => strtolower((string) $status))
            ->unique()
            ->values();

        $summaryStatus = 'idle';

        if ($statuses->contains('blocked')) {
            $summaryStatus = 'blocked';
        } elseif ($statuses->contains('error')) {
            $summaryStatus = 'error';
        } elseif ($statuses->contains('processing')) {
            $summaryStatus = 'processing';
        } elseif ($statuses->contains('pending')) {
            $summaryStatus = 'pending';
        } elseif ($statuses->contains('exported') && $statuses->every(fn ($status) => in_array($status, ['exported', 'not_required', 'idle'], true))) {
            $summaryStatus = 'exported';
        }

        $exportedAt = null;

        if ($summaryStatus === 'exported') {
            $exportedAt = $quote->suppliers
                ->pluck('protheus_exported_at')
                ->filter()
                ->max();
        }

        $quote->fill([
            'protheus_export_status' => $summaryStatus,
            'protheus_exported_at' => $exportedAt,
        ]);

        if ($quote->isDirty()) {
            $this->saveModelWithStringTimestamps($quote);
        }
    }

    /**
     * Valida e ajusta as informações necessárias para exportação.
     *
     * @return array{0: string, 1: string|null}
     */
    protected function synchronizeSupplierData(PurchaseQuote $quote, PurchaseQuoteSupplier $supplier, Collection $items): array
    {
        if ($items->isEmpty()) {
            return ['blocked', 'Nenhum item foi selecionado para o fornecedor.'];
        }

        $issues = [];

        if (blank($supplier->payment_condition_code)) {
            $issues[] = sprintf('Informe a condição de pagamento para o fornecedor "%s".', $supplier->supplier_name ?? $supplier->id);
        }

        if (blank($supplier->freight_type)) {
            $issues[] = sprintf('Informe o tipo de frete para o fornecedor "%s".', $supplier->supplier_name ?? $supplier->id);
        }

        if (blank($quote->nature_operation_code)) {
            $issues[] = 'Informe a natureza de operação antes de exportar.';
        }

        if (blank($quote->nature_operation_cfop)) {
            $issues[] = 'Informe o CFOP da natureza de operação antes de exportar.';
        }

        if (blank($supplier->supplier_code)) {
            $issues[] = 'Fornecedor sem código Protheus vinculado.';
        }

        foreach ($items as $item) {
            /** @var PurchaseQuoteItem $item */
            $supplierItem = $supplier->items
                ? $supplier->items->firstWhere('purchase_quote_item_id', $item->id)
                : null;

            if (blank($item->product_code)) {
                $issues[] = sprintf('Item "%s" sem código do produto.', $item->description ?? $item->id);
            }

            if ($item->quantity === null || $item->quantity <= 0) {
                $issues[] = sprintf('Item "%s" com quantidade inválida.', $item->description ?? $item->id);
            }

            if (blank($item->unit)) {
                $item->unit = 'UN';
            }

            if (blank($item->cost_center_code) && filled($quote->main_cost_center_code)) {
                $item->cost_center_code = $quote->main_cost_center_code;
            }

            if (blank($item->cost_center_description) && filled($quote->main_cost_center_description)) {
                $item->cost_center_description = $quote->main_cost_center_description;
            }

            $unitCost = $item->selected_unit_cost;

            if ($unitCost === null && $supplierItem && $supplierItem->unit_cost !== null) {
                $unitCost = (float) $supplierItem->unit_cost;
                $item->selected_unit_cost = $unitCost;
            }

            if ($unitCost === null) {
                $issues[] = sprintf('Item "%s" sem custo unitário selecionado.', $item->description ?? $item->id);
            } else {
                $totalCost = $item->selected_total_cost;

                if ($totalCost === null) {
                    $quantity = (float) ($item->quantity ?? 0);
                    $totalCost = round($unitCost * $quantity, 4);
                    $item->selected_total_cost = $totalCost;
                }
            }

            if (!$item->cost_center_code) {
                $issues[] = sprintf('Item "%s" sem centro de custo definido.', $item->description ?? $item->id);
            }

            if (blank($item->tes_code)) {
                $issues[] = sprintf('Item "%s" sem TES selecionado.', $item->description ?? $item->id);
            }

            if (blank($item->cfop_code) && filled($quote->nature_operation_cfop)) {
                $item->cfop_code = $quote->nature_operation_cfop;
            }

            if (blank($item->cfop_code)) {
                $issues[] = sprintf('Item "%s" sem CFOP definido.', $item->description ?? $item->id);
            }

            if ($item->isDirty()) {
                try {
                    $this->saveModelWithStringTimestamps($item);
                } catch (\Throwable $exception) {
                    Log::warning('Falha ao atualizar item durante sincronização com Protheus', [
                        'item_id' => $item->id,
                        'quote_id' => $quote->id,
                        'supplier_id' => $supplier->id,
                        'error' => $exception->getMessage(),
                    ]);

                    $issues[] = sprintf(
                        'Não foi possível ajustar automaticamente os dados do item "%s".',
                        $item->description ?? $item->id
                    );
                }
            }
        }

        if (!empty($issues)) {
            $message = implode(PHP_EOL, array_unique($issues));

            return ['blocked', $message];
        }

        return ['pending', null];
    }
}


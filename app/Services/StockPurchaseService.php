<?php

namespace App\Services;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteItem;
use App\Models\StockProduct;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockPurchaseService
{
    /**
     * Entrada de produtos no estoque a partir de cotação aprovada
     */
    public function entrarProdutosPorCompra(PurchaseQuote $quote): void
    {
        DB::beginTransaction();

        try {
            $companyId = $quote->company_id;

            // Buscar itens da cotação que foram selecionados
            $items = PurchaseQuoteItem::where('purchase_quote_id', $quote->id)
                ->whereHas('suppliers', function($query) {
                    // Considerar apenas itens com fornecedor selecionado
                    $query->where('selected', true);
                })
                ->get();

            foreach ($items as $item) {
                // Buscar fornecedor selecionado para este item
                $selectedSupplier = $item->suppliers()
                    ->where('selected', true)
                    ->first();

                if (!$selectedSupplier) {
                    continue;
                }

                // Verificar/criar produto
                $product = StockProduct::firstOrCreate(
                    [
                        'code' => $item->product_code ?? $item->description,
                        'company_id' => $companyId,
                    ],
                    [
                        'description' => $item->description,
                        'reference' => $item->product_reference ?? null,
                        'unit' => $item->unit ?? 'UN',
                        'active' => true,
                    ]
                );

                // Definir local de estoque (pode vir do item, cotação ou padrão)
                // Por enquanto, usar primeiro local ativo da empresa
                $location = DB::table('stock_locations')
                    ->where('company_id', $companyId)
                    ->where('active', true)
                    ->first();

                if (!$location) {
                    continue; // Pular se não houver local
                }

                // Criar/atualizar estoque
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

                $quantity = (float) $selectedSupplier->quantity ?? 1;
                $cost = (float) $selectedSupplier->unit_price ?? 0;
                $totalCost = $cost * $quantity;

                $quantityBefore = $stock->quantity_available;
                $quantityAfter = $quantityBefore + $quantity;

                // Atualizar estoque
                $stock->update([
                    'quantity_available' => $quantityAfter,
                    'quantity_total' => $stock->quantity_total + $quantity,
                    'last_movement_at' => Carbon::now(),
                ]);

                // Criar movimentação
                StockMovement::create([
                    'stock_id' => $stock->id,
                    'stock_product_id' => $product->id,
                    'stock_location_id' => $location->id,
                    'movement_type' => 'entrada',
                    'quantity' => $quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'reference_type' => 'compra',
                    'reference_id' => $quote->id,
                    'reference_number' => $quote->quote_number ?? (string) $quote->id,
                    'cost' => $cost,
                    'total_cost' => $totalCost,
                    'observation' => "Entrada por compra - Cotação #{$quote->id}",
                    'user_id' => auth()->id(),
                    'company_id' => $companyId,
                    'movement_date' => Carbon::now()->toDateString(),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}


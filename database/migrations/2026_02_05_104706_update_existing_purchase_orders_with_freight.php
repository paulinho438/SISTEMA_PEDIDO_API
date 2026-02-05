<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Atualizar pedidos de compra existentes com frete da cotação
        // Buscar todos os pedidos que não têm freight_value ou têm NULL
        $orders = DB::table('purchase_orders')
            ->whereNull('freight_value')
            ->orWhere('freight_value', 0)
            ->get();

        foreach ($orders as $order) {
            // Buscar o fornecedor da cotação vinculado ao pedido
            $supplier = DB::table('purchase_quote_suppliers')
                ->where('id', $order->purchase_quote_supplier_id)
                ->first();

            if ($supplier) {
                $freightType = $supplier->freight_type ?? null;
                $freightValue = $supplier->freight_value ?? 0;

                // Calcular total dos itens do pedido
                $totalItems = DB::table('purchase_order_items')
                    ->where('purchase_order_id', $order->id)
                    ->sum('total_price');

                // Adicionar frete ao total
                $newTotalAmount = $totalItems + $freightValue;

                // Atualizar o pedido com frete e novo total
                DB::table('purchase_orders')
                    ->where('id', $order->id)
                    ->update([
                        'freight_type' => $freightType,
                        'freight_value' => $freightValue,
                        'total_amount' => $newTotalAmount,
                        'updated_at' => now()->format('Y-m-d H:i:s'),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não há como reverter completamente, mas podemos zerar os valores de frete
        // e recalcular o total sem frete
        $orders = DB::table('purchase_orders')
            ->whereNotNull('freight_value')
            ->where('freight_value', '>', 0)
            ->get();

        foreach ($orders as $order) {
            // Recalcular total sem frete
            $totalItems = DB::table('purchase_order_items')
                ->where('purchase_order_id', $order->id)
                ->sum('total_price');

            DB::table('purchase_orders')
                ->where('id', $order->id)
                ->update([
                    'freight_type' => null,
                    'freight_value' => null,
                    'total_amount' => $totalItems,
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                ]);
        }
    }
};

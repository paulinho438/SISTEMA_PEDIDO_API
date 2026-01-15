<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('purchase_quote_item_id')->constrained('purchase_order_items')->onDelete('no action');
            $table->foreignId('stock_location_id')->nullable()->after('stock_product_id')->constrained('stock_locations')->onDelete('no action');
            $table->index('purchase_order_item_id');
            $table->index('stock_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_item_id']);
            $table->dropIndex(['purchase_order_item_id']);
            $table->dropColumn('purchase_order_item_id');
            
            $table->dropForeign(['stock_location_id']);
            $table->dropIndex(['stock_location_id']);
            $table->dropColumn('stock_location_id');
        });
    }
};

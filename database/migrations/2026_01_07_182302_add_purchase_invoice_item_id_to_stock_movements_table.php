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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('purchase_invoice_item_id')->nullable()->after('reference_number')->constrained('purchase_invoice_items')->nullOnDelete();
            $table->index('purchase_invoice_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_item_id']);
            $table->dropIndex(['purchase_invoice_item_id']);
            $table->dropColumn('purchase_invoice_item_id');
        });
    }
};

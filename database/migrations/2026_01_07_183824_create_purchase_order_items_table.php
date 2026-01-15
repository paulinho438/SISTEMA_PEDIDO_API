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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->onDelete('no action');
            $table->foreignId('purchase_quote_item_id')->constrained('purchase_quote_items')->onDelete('no action');
            $table->foreignId('purchase_quote_supplier_item_id')->nullable()->constrained('purchase_quote_supplier_items')->onDelete('no action');
            $table->string('product_code', 100)->nullable(); // Código do produto
            $table->string('product_description'); // Descrição do produto
            $table->decimal('quantity', 15, 4); // Quantidade
            $table->string('unit', 20)->nullable(); // Unidade de medida
            $table->decimal('unit_price', 15, 4); // Preço unitário
            $table->decimal('total_price', 15, 4); // Preço total (quantity * unit_price)
            $table->decimal('ipi', 8, 4)->nullable(); // IPI
            $table->decimal('icms', 8, 4)->nullable(); // ICMS
            $table->decimal('final_cost', 15, 4)->nullable(); // Custo final (com impostos)
            $table->text('observation')->nullable(); // Observações do item
            $table->timestamps();
            
            $table->index('purchase_order_id');
            $table->index('purchase_quote_id');
            $table->index('purchase_quote_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};

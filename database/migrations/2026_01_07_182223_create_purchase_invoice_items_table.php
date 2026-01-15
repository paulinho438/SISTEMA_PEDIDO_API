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
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_quote_id')->nullable()->constrained('purchase_quotes')->onDelete('no action');
            $table->foreignId('purchase_quote_item_id')->nullable()->constrained('purchase_quote_items')->onDelete('no action');
            $table->foreignId('stock_product_id')->nullable()->constrained('stock_products')->onDelete('no action');
            $table->string('product_code', 100)->nullable(); // Código do produto na nota fiscal
            $table->string('product_description'); // Descrição do produto
            $table->decimal('quantity', 15, 4); // Quantidade
            $table->string('unit', 20)->nullable(); // Unidade de medida
            $table->decimal('unit_price', 15, 4); // Preço unitário
            $table->decimal('total_price', 15, 4); // Preço total (quantity * unit_price)
            $table->text('observation')->nullable(); // Observações do item
            $table->timestamps();
            
            $table->index('purchase_invoice_id');
            $table->index('purchase_quote_id');
            $table->index('stock_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};

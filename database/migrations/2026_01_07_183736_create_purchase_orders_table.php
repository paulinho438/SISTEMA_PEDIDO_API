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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique(); // Número do pedido
            $table->date('order_date'); // Data do pedido
            $table->date('expected_delivery_date')->nullable(); // Data prevista de entrega
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->onDelete('no action'); // Cotação origem
            $table->foreignId('purchase_quote_supplier_id')->constrained('purchase_quote_suppliers')->onDelete('no action'); // Fornecedor selecionado
            $table->string('supplier_name'); // Nome do fornecedor (denormalizado)
            $table->string('supplier_document', 20)->nullable(); // CNPJ/CPF do fornecedor
            $table->string('supplier_code', 60)->nullable(); // Código do fornecedor
            $table->string('vendor_name')->nullable(); // Nome do vendedor
            $table->string('vendor_phone', 50)->nullable(); // Telefone do vendedor
            $table->string('vendor_email')->nullable(); // Email do vendedor
            $table->string('proposal_number', 100)->nullable(); // Número da proposta
            $table->decimal('total_amount', 15, 2)->default(0); // Valor total do pedido
            $table->string('status', 50)->default('pendente'); // Status: pendente, parcialmente_recebido, recebido, cancelado
            $table->text('observation')->nullable(); // Observações
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('no action');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('no action');
            $table->timestamps();
            
            $table->index('order_number');
            $table->index('order_date');
            $table->index('purchase_quote_id');
            $table->index('purchase_quote_supplier_id');
            $table->index('status');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};

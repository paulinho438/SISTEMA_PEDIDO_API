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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique(); // Número da nota fiscal
            $table->string('invoice_series', 10)->nullable(); // Série da nota fiscal
            $table->date('invoice_date'); // Data de emissão da nota fiscal
            $table->date('received_date')->nullable(); // Data de recebimento
            $table->foreignId('purchase_quote_id')->nullable()->constrained('purchase_quotes')->nullOnDelete(); // Relacionamento com cotação
            $table->string('supplier_name')->nullable(); // Nome do fornecedor
            $table->string('supplier_document', 20)->nullable(); // CNPJ/CPF do fornecedor
            $table->decimal('total_amount', 15, 2)->default(0); // Valor total da nota fiscal
            $table->text('observation')->nullable(); // Observações
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('no action');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('no action');
            $table->timestamps();
            
            $table->index('invoice_number');
            $table->index('invoice_date');
            $table->index('purchase_quote_id');
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Termo de Responsabilidade: ferramentas entregues a colaboradores (montagem, etc.)
     * até o fim da obra; ao devolver, itens retornam ao estoque.
     */
    public function up(): void
    {
        Schema::create('responsibility_terms', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique();
            $table->string('responsible_name', 255);
            $table->string('cpf', 20)->nullable();
            $table->string('project', 255)->nullable()->comment('Nome da obra/projeto');
            $table->foreignId('stock_location_id')->constrained('stock_locations')->onDelete('no action');
            $table->string('status', 20)->default('aberto')->comment('aberto, devolvido');
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('stock_location_id');
            $table->index('created_by');
        });

        Schema::create('responsibility_term_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('responsibility_term_id')->constrained('responsibility_terms')->onDelete('cascade');
            $table->foreignId('stock_product_id')->constrained('stock_products')->onDelete('no action');
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('no action');
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->index('responsibility_term_id');
            $table->index('stock_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsibility_term_items');
        Schema::dropIfExists('responsibility_terms');
    }
};

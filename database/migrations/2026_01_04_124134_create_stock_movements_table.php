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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('no action');
            $table->foreignId('stock_product_id')->constrained('stock_products')->onDelete('no action');
            $table->foreignId('stock_location_id')->constrained('stock_locations')->onDelete('no action');
            $table->enum('movement_type', ['entrada', 'saida', 'ajuste', 'transferencia']);
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_before', 15, 4);
            $table->decimal('quantity_after', 15, 4);
            $table->enum('reference_type', ['compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro'])->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            $table->text('observation')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Foreign key será criada manualmente para evitar ciclos de cascade
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->date('movement_date');
            $table->timestamps();
            
            $table->index('stock_id');
            $table->index('stock_product_id');
            $table->index('stock_location_id');
            $table->index('movement_type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('company_id');
            $table->index('movement_date');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

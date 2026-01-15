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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_product_id')->constrained('stock_products')->onDelete('no action');
            $table->foreignId('stock_location_id')->constrained('stock_locations')->onDelete('no action');
            $table->decimal('quantity_available', 15, 4)->default(0.0000);
            $table->decimal('quantity_reserved', 15, 4)->default(0.0000);
            $table->decimal('quantity_total', 15, 4)->default(0.0000);
            $table->decimal('min_stock', 15, 4)->nullable();
            $table->decimal('max_stock', 15, 4)->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->timestamps();
            
            $table->unique(['stock_product_id', 'stock_location_id', 'company_id'], 'unique_product_location_company');
            $table->index('stock_product_id');
            $table->index('stock_location_id');
            $table->index('company_id');
            $table->index('quantity_available');
            $table->index('quantity_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};

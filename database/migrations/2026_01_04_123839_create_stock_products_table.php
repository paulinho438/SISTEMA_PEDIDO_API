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
        Schema::create('stock_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('reference', 100)->nullable();
            $table->string('description', 255);
            $table->string('unit', 20);
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->timestamps();
            
            $table->index('code');
            $table->index('company_id');
            $table->index('active');
            $table->index('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_products');
    }
};

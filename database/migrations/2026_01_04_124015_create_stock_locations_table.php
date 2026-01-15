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
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->timestamps();
            
            $table->unique(['code', 'company_id'], 'unique_code_company');
            $table->index('company_id');
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};

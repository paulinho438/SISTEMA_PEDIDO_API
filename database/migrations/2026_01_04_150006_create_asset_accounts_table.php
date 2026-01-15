<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('type', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('no action');
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_accounts');
    }
};


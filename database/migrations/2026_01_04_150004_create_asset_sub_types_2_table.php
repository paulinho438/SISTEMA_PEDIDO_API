<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_sub_types_2', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('asset_sub_type_1_id')->nullable()->constrained('asset_sub_types_1')->onDelete('set null');
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('no action');
            $table->timestamps();
            
            $table->index('asset_sub_type_1_id');
            $table->index('company_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_sub_types_2');
    }
};


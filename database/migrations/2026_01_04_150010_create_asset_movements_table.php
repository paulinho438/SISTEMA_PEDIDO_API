<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            // Foreign key será criada manualmente para evitar ciclos de cascade
            $table->string('movement_type', 50);
            $table->date('movement_date');
            $table->unsignedBigInteger('from_branch_id')->nullable();
            $table->unsignedBigInteger('to_branch_id')->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
            // Foreign keys serão criadas manualmente para evitar ciclos de cascade
            $table->unsignedBigInteger('from_responsible_id')->nullable();
            $table->unsignedBigInteger('to_responsible_id')->nullable();
            // Foreign keys serão criadas manualmente para evitar ciclos de cascade
            $table->unsignedBigInteger('from_cost_center_id')->nullable();
            $table->unsignedBigInteger('to_cost_center_id')->nullable();
            // Foreign keys serão adicionadas manualmente se a tabela costcenter existir
            $table->text('observation')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Foreign key será criada manualmente para evitar ciclos de cascade
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->timestamps();
            
            $table->index('asset_id');
            $table->index('movement_type');
            $table->index('movement_date');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};


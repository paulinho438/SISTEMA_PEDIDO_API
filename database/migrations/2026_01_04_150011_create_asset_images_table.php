<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->string('image_path', 500);
            $table->string('image_name', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('order_index')->default(0);
            $table->timestamps();
            
            $table->index('asset_id');
            $table->index('is_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_images');
    }
};


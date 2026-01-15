<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('custom_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->longText('content');
            $table->enum('operation',['index', 'create', 'store', 'edit', 'update', 'destroy', 'custom', 'error']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->datetime('created_at', 2)->nullable();
            $table->datetime('updated_at', 2)->nullable();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_logs');
    }
};

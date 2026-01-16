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
        Schema::create('purchase_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->string('old_status', 50);
            $table->string('new_status', 50);
            $table->text('justification')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('no action');
            $table->timestamps();
            
            $table->index('purchase_order_id');
            $table->index('new_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_order_status_histories');
    }
};

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
        Schema::table('purchase_quote_suppliers', function (Blueprint $table) {
            $table->decimal('freight_value', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_quote_suppliers', function (Blueprint $table) {
            $table->dropColumn(['freight_value', 'discount']);
        });
    }
};

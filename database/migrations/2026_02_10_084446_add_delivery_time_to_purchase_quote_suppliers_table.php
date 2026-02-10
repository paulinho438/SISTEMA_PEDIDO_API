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
            $table->string('delivery_time', 100)->nullable()->after('payment_condition_description');
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
            $table->dropColumn('delivery_time');
        });
    }
};

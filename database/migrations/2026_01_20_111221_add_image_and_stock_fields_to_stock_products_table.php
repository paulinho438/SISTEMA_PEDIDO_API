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
        Schema::table('stock_products', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('active');
            $table->integer('min_stock')->nullable()->after('image_path');
            $table->integer('max_stock')->nullable()->after('min_stock');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_products', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'min_stock', 'max_stock']);
        });
    }
};

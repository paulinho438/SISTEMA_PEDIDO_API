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
        // Verificar se as colunas existem como decimal antes de alterar
        if (Schema::hasColumn('stock_products', 'min_stock')) {
            Schema::table('stock_products', function (Blueprint $table) {
                // Remover a coluna decimal
                $table->dropColumn('min_stock');
            });
        }
        
        if (Schema::hasColumn('stock_products', 'max_stock')) {
            Schema::table('stock_products', function (Blueprint $table) {
                // Remover a coluna decimal
                $table->dropColumn('max_stock');
            });
        }
        
        // Adicionar como integer
        Schema::table('stock_products', function (Blueprint $table) {
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
        // Reverter para decimal (caso precise)
        if (Schema::hasColumn('stock_products', 'min_stock')) {
            Schema::table('stock_products', function (Blueprint $table) {
                $table->dropColumn('min_stock');
            });
        }
        
        if (Schema::hasColumn('stock_products', 'max_stock')) {
            Schema::table('stock_products', function (Blueprint $table) {
                $table->dropColumn('max_stock');
            });
        }
        
        Schema::table('stock_products', function (Blueprint $table) {
            $table->decimal('min_stock', 10, 4)->nullable()->after('image_path');
            $table->decimal('max_stock', 10, 4)->nullable()->after('min_stock');
        });
    }
};

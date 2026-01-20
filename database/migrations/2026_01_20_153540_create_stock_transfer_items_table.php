<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Usar DB::statement para compatibilidade com SQL Server
        DB::statement("
            CREATE TABLE [stock_transfer_items] (
                [id] BIGINT IDENTITY(1,1) PRIMARY KEY,
                [stock_transfer_id] BIGINT NOT NULL,
                [stock_id] BIGINT NOT NULL,
                [stock_product_id] BIGINT NOT NULL,
                [quantity] DECIMAL(10,2) NOT NULL,
                [quantity_available_before] DECIMAL(10,2) NOT NULL,
                [created_at] DATETIME2 NULL,
                [updated_at] DATETIME2 NULL,
                CONSTRAINT [fk_stock_transfer_items_transfer] FOREIGN KEY ([stock_transfer_id]) REFERENCES [stock_transfers]([id]) ON DELETE CASCADE,
                CONSTRAINT [fk_stock_transfer_items_stock] FOREIGN KEY ([stock_id]) REFERENCES [stocks]([id]),
                CONSTRAINT [fk_stock_transfer_items_product] FOREIGN KEY ([stock_product_id]) REFERENCES [stock_products]([id])
            )
        ");

        // Criar índices
        DB::statement("CREATE INDEX [idx_stock_transfer_items_transfer] ON [stock_transfer_items]([stock_transfer_id])");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS [stock_transfer_items]");
    }
};


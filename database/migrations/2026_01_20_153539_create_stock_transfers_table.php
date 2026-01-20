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
            CREATE TABLE [stock_transfers] (
                [id] BIGINT IDENTITY(1,1) PRIMARY KEY,
                [transfer_number] VARCHAR(50) NULL,
                [origin_location_id] BIGINT NOT NULL,
                [destination_location_id] BIGINT NOT NULL,
                [driver_name] VARCHAR(255) NULL,
                [license_plate] VARCHAR(20) NULL,
                [status] VARCHAR(20) NOT NULL DEFAULT 'pendente',
                [observation] TEXT NULL,
                [user_id] BIGINT NOT NULL,
                [company_id] BIGINT NOT NULL,
                [created_at] DATETIME2 NULL,
                [updated_at] DATETIME2 NULL,
                CONSTRAINT [fk_stock_transfers_origin] FOREIGN KEY ([origin_location_id]) REFERENCES [stock_locations]([id]),
                CONSTRAINT [fk_stock_transfers_destination] FOREIGN KEY ([destination_location_id]) REFERENCES [stock_locations]([id]),
                CONSTRAINT [fk_stock_transfers_user] FOREIGN KEY ([user_id]) REFERENCES [users]([id]),
                CONSTRAINT [fk_stock_transfers_company] FOREIGN KEY ([company_id]) REFERENCES [companies]([id])
            )
        ");

        // Criar índice para status
        DB::statement("CREATE INDEX [idx_stock_transfers_status] ON [stock_transfers]([status])");
        DB::statement("CREATE INDEX [idx_stock_transfers_company] ON [stock_transfers]([company_id])");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS [stock_transfers]");
    }
};

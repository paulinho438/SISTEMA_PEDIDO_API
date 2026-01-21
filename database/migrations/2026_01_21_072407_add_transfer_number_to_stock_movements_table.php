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
        // Adicionar coluna transfer_number na tabela stock_movements
        DB::statement('
            IF NOT EXISTS (
                SELECT * FROM sys.columns 
                WHERE object_id = OBJECT_ID(\'stock_movements\') 
                AND name = \'transfer_number\'
            )
            BEGIN
                ALTER TABLE [stock_movements] 
                ADD [transfer_number] VARCHAR(50) NULL
            END
        ');
        
        // Adicionar índice para melhorar performance nas consultas
        DB::statement('
            IF NOT EXISTS (
                SELECT * FROM sys.indexes 
                WHERE name = \'IX_stock_movements_transfer_number\' 
                AND object_id = OBJECT_ID(\'stock_movements\')
            )
            BEGIN
                CREATE INDEX [IX_stock_movements_transfer_number] 
                ON [stock_movements] ([transfer_number])
            END
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remover índice
        DB::statement('
            IF EXISTS (
                SELECT * FROM sys.indexes 
                WHERE name = \'IX_stock_movements_transfer_number\' 
                AND object_id = OBJECT_ID(\'stock_movements\')
            )
            BEGIN
                DROP INDEX [IX_stock_movements_transfer_number] ON [stock_movements]
            END
        ');
        
        // Remover coluna
        DB::statement('
            IF EXISTS (
                SELECT * FROM sys.columns 
                WHERE object_id = OBJECT_ID(\'stock_movements\') 
                AND name = \'transfer_number\'
            )
            BEGIN
                ALTER TABLE [stock_movements] 
                DROP COLUMN [transfer_number]
            END
        ');
    }
};

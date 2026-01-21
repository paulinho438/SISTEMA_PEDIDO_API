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
        // Primeiro, remover a foreign key antiga
        DB::statement('
            IF EXISTS (
                SELECT * 
                FROM sys.foreign_keys 
                WHERE name = \'FK_assets_location_id_stock_locations\'
                AND parent_object_id = OBJECT_ID(\'assets\')
            )
            BEGIN
                ALTER TABLE [assets] DROP CONSTRAINT [FK_assets_location_id_stock_locations]
            END
        ');

        // Limpar location_id inválidos (que não existem na nova tabela asset_locations)
        // Definir como NULL para todos os registros que não têm correspondência
        DB::statement('
            UPDATE [assets]
            SET [location_id] = NULL
            WHERE [location_id] IS NOT NULL
            AND [location_id] NOT IN (SELECT [id] FROM [asset_locations])
        ');

        // Alterar a foreign key para apontar para asset_locations
        DB::statement('
            IF NOT EXISTS (
                SELECT * 
                FROM sys.foreign_keys 
                WHERE name = \'FK_assets_location_id_asset_locations\'
                AND parent_object_id = OBJECT_ID(\'assets\')
            )
            BEGIN
                ALTER TABLE [assets] 
                ADD CONSTRAINT [FK_assets_location_id_asset_locations] 
                FOREIGN KEY ([location_id]) 
                REFERENCES [asset_locations]([id]) 
                ON DELETE SET NULL
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
        // Remover a foreign key para asset_locations
        DB::statement('
            IF EXISTS (
                SELECT * 
                FROM sys.foreign_keys 
                WHERE name = \'FK_assets_location_id_asset_locations\'
                AND parent_object_id = OBJECT_ID(\'assets\')
            )
            BEGIN
                ALTER TABLE [assets] DROP CONSTRAINT [FK_assets_location_id_asset_locations]
            END
        ');

        // Restaurar a foreign key para stock_locations
        DB::statement('
            IF NOT EXISTS (
                SELECT * 
                FROM sys.foreign_keys 
                WHERE name = \'FK_assets_location_id_stock_locations\'
                AND parent_object_id = OBJECT_ID(\'assets\')
            )
            BEGIN
                ALTER TABLE [assets] 
                ADD CONSTRAINT [FK_assets_location_id_stock_locations] 
                FOREIGN KEY ([location_id]) 
                REFERENCES [stock_locations]([id]) 
                ON DELETE SET NULL
            END
        ');
    }
};

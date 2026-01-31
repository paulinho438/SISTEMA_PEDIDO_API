<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Garante que assets.location_id aponte para asset_locations (não stock_locations).
     * O formulário de ativos usa locais de ativos/ativos/locais = asset_locations.
     */
    public function up(): void
    {
        // Remover FK que aponta para stock_locations (qualquer nome que tenha)
        DB::statement("
            DECLARE @fkName NVARCHAR(255);
            SELECT @fkName = fk.name
            FROM sys.foreign_keys fk
            INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
            INNER JOIN sys.tables t ON fkc.referenced_object_id = t.object_id
            WHERE fk.parent_object_id = OBJECT_ID('assets')
            AND fkc.parent_column_id = (SELECT column_id FROM sys.columns WHERE object_id = OBJECT_ID('assets') AND name = 'location_id')
            AND t.name = 'stock_locations';
            IF @fkName IS NOT NULL
            BEGIN
                EXEC('ALTER TABLE [assets] DROP CONSTRAINT [' + @fkName + ']');
            END
        ");

        // Limpar location_id que não existem em asset_locations
        DB::statement("
            UPDATE [assets]
            SET [location_id] = NULL
            WHERE [location_id] IS NOT NULL
            AND [location_id] NOT IN (SELECT [id] FROM [asset_locations])
        ");

        // Adicionar FK para asset_locations (se ainda não existir)
        DB::statement("
            IF NOT EXISTS (
                SELECT 1 FROM sys.foreign_keys fk
                INNER JOIN sys.tables t ON fk.referenced_object_id = t.object_id
                WHERE fk.parent_object_id = OBJECT_ID('assets')
                AND t.name = 'asset_locations'
            )
            BEGIN
                ALTER TABLE [assets]
                ADD CONSTRAINT [FK_assets_location_id_asset_locations]
                FOREIGN KEY ([location_id])
                REFERENCES [asset_locations]([id])
                ON DELETE SET NULL;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover FK para asset_locations
        DB::statement("
            IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_assets_location_id_asset_locations' AND parent_object_id = OBJECT_ID('assets'))
            BEGIN
                ALTER TABLE [assets] DROP CONSTRAINT [FK_assets_location_id_asset_locations];
            END
        ");

        // Restaurar FK para stock_locations
        DB::statement("
            IF NOT EXISTS (
                SELECT 1 FROM sys.foreign_keys fk
                INNER JOIN sys.tables t ON fk.referenced_object_id = t.object_id
                WHERE fk.parent_object_id = OBJECT_ID('assets')
                AND t.name = 'stock_locations'
            )
            BEGIN
                ALTER TABLE [assets]
                ADD CONSTRAINT [assets_location_id_foreign]
                FOREIGN KEY ([location_id])
                REFERENCES [stock_locations]([id])
                ON DELETE SET NULL;
            END
        ");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adiciona 'termo_responsabilidade' ao reference_type de stock_movements.
     * SQL Server: remove CHECK constraint existente e cria nova incluindo o valor.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            // Buscar nome da constraint CHECK em reference_type (pode variar)
            $constraintName = $this->findCheckConstraintName();
            if ($constraintName) {
                DB::statement("ALTER TABLE [stock_movements] DROP CONSTRAINT [{$constraintName}]");
            }
            DB::statement("ALTER TABLE [stock_movements] ADD CONSTRAINT [CK_stock_movements_reference_type] CHECK ([reference_type] IN ('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro', 'termo_responsabilidade') OR [reference_type] IS NULL)");
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN reference_type ENUM('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro', 'termo_responsabilidade') NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlsrv') {
            DB::statement("ALTER TABLE [stock_movements] DROP CONSTRAINT [CK_stock_movements_reference_type]");
            DB::statement("ALTER TABLE [stock_movements] ADD CONSTRAINT [CK_stock_movements_reference_type_old] CHECK ([reference_type] IN ('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro') OR [reference_type] IS NULL)");
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN reference_type ENUM('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro') NULL");
        }
    }

    private function findCheckConstraintName(): ?string
    {
        $results = DB::select("
            SELECT name, OBJECT_DEFINITION(object_id) AS definition
            FROM sys.check_constraints
            WHERE parent_object_id = OBJECT_ID('stock_movements')
        ");
        foreach ($results as $row) {
            $def = $row->definition ?? '';
            if (stripos($def, 'reference_type') !== false || stripos($def, 'refer') !== false) {
                return $row->name;
            }
        }
        return !empty($results) ? ($results[0]->name ?? null) : null;
    }
};

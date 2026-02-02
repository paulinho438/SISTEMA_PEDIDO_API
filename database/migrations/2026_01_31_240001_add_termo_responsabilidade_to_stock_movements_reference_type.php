<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adiciona 'termo_responsabilidade' ao reference_type de stock_movements.
     * MySQL: altera enum. SQL Server: coluna pode ser varchar; se houver check, ajustar manualmente se necessário.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN reference_type ENUM('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro', 'termo_responsabilidade') NULL");
        }
        // SQL Server: Laravel enum muitas vezes vira varchar; inserir 'termo_responsabilidade' deve funcionar.
        // Se existir CHECK constraint, pode ser necessário removê-lo/recriá-lo manualmente.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY COLUMN reference_type ENUM('compra', 'solicitacao', 'ajuste_manual', 'transferencia', 'outro') NULL");
        }
    }
};

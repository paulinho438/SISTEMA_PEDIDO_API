<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Verificar se o status já existe
        $exists = DB::table('purchase_quote_statuses')
            ->where('slug', 'reprovado')
            ->exists();

        if (!$exists) {
            // Preparar timestamps como strings para SQL Server
            $now = now()->format('Y-m-d H:i:s');
            
            // Inserir novo status "reprovado" usando DB::statement para garantir compatibilidade com SQL Server
            DB::statement("
                INSERT INTO [purchase_quote_statuses] 
                ([slug], [label], [description], [required_profile], [order], [created_at], [updated_at]) 
                VALUES (?, ?, ?, ?, ?, CAST(? AS DATETIME2), CAST(? AS DATETIME2))
            ", [
                'reprovado',
                'Reprovado',
                'Solicitação reprovada e pode ser alterada.',
                null,
                99,
                $now,
                $now
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remover o status
        DB::table('purchase_quote_statuses')
            ->where('slug', 'reprovado')
            ->delete();
    }
};


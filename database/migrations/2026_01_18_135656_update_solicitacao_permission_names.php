<?php

use Illuminate\Database\Migrations\Migration;
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
        // Atualizar nome da permissão "Criar Cotação" para "Criar Solicitação"
        DB::table('permitems')
            ->where('slug', 'create_cotacoes')
            ->update(['name' => 'Criar Solicitação']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverter nome da permissão "Criar Solicitação" para "Criar Cotação"
        DB::table('permitems')
            ->where('slug', 'create_cotacoes')
            ->update(['name' => 'Criar Cotação']);
    }
};

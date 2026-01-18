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
        // Atualizar nome da permissão "Editar Cotações" para "Editar Solicitação"
        // e mover do grupo "cotacoes_edicao" para "cotacoes_solicitacao"
        DB::table('permitems')
            ->where('slug', 'edit_cotacoes')
            ->update([
                'name' => 'Editar Solicitação',
                'group' => 'cotacoes_solicitacao'
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverter nome da permissão "Editar Solicitação" para "Editar Cotações"
        // e mover de volta do grupo "cotacoes_solicitacao" para "cotacoes_edicao"
        DB::table('permitems')
            ->where('slug', 'edit_cotacoes')
            ->update([
                'name' => 'Editar Cotações',
                'group' => 'cotacoes_edicao'
            ]);
    }
};

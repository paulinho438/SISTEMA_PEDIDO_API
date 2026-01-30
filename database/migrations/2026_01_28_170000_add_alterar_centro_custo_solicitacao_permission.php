<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('permitems')->where('slug', 'alterar_centro_custo_solicitacao')->exists();
        if (!$exists) {
            DB::table('permitems')->insert([
                'name' => 'Alterar centro de custo da solicitação/cotação',
                'slug' => 'alterar_centro_custo_solicitacao',
                'group' => 'solicitacoes',
            ]);
        }
    }

    public function down(): void
    {
        $perm = DB::table('permitems')->where('slug', 'alterar_centro_custo_solicitacao')->first();
        if ($perm) {
            DB::table('permgroup_permitem')->where('permitem_id', $perm->id)->delete();
            DB::table('permitems')->where('slug', 'alterar_centro_custo_solicitacao')->delete();
        }
    }
};

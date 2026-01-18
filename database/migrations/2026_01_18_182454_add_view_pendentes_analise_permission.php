<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar se a permissão já existe
        $permissionExists = DB::table('permitems')
            ->where('slug', 'view_pendentes_analise')
            ->exists();

        if (!$permissionExists) {
            // Inserir a nova permissão (tabela permitems não tem created_at/updated_at)
            DB::table('permitems')->insert([
                'name' => 'Visualizar Pendentes de Análise',
                'slug' => 'view_pendentes_analise',
                'group' => 'cotacoes_aprovacao_nivel',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover permissões da tabela pivot primeiro
        $permission = DB::table('permitems')->where('slug', 'view_pendentes_analise')->first();
        if ($permission) {
            DB::table('permgroup_permitem')->where('permitem_id', $permission->id)->delete();
            DB::table('permitems')->where('slug', 'view_pendentes_analise')->delete();
        }
    }
};

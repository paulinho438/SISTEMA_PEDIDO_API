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
            ->where('slug', 'view_all_solicitacoes')
            ->exists();

        if (!$permissionExists) {
            // Inserir a nova permissão
            DB::table('permitems')->insert([
                'name' => 'Visualizar Todas as Solicitações',
                'slug' => 'view_all_solicitacoes',
                'group' => 'solicitacoes',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover permissões da tabela pivot primeiro
        $permission = DB::table('permitems')->where('slug', 'view_all_solicitacoes')->first();
        if ($permission) {
            DB::table('permgroup_permitem')->where('permitem_id', $permission->id)->delete();
            DB::table('permitems')->where('slug', 'view_all_solicitacoes')->delete();
        }
    }
};


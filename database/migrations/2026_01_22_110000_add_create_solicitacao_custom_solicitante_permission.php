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
            ->where('slug', 'create_solicitacao_custom_solicitante')
            ->exists();

        if (!$permissionExists) {
            // Inserir a nova permissão
            DB::table('permitems')->insert([
                'name' => 'Criar Solicitação com Solicitante Customizado',
                'slug' => 'create_solicitacao_custom_solicitante',
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
        $permission = DB::table('permitems')->where('slug', 'create_solicitacao_custom_solicitante')->first();
        if ($permission) {
            DB::table('permgroup_permitem')->where('permitem_id', $permission->id)->delete();
            DB::table('permitems')->where('slug', 'create_solicitacao_custom_solicitante')->delete();
        }
    }
};


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
        $permissions = [
            [
                'name' => 'Visualizar Descrições Padrão de Ativos',
                'slug' => 'view_ativos_descricoes_padrao',
                'group' => 'ativos',
            ],
            [
                'name' => 'Criar Descrições Padrão de Ativos',
                'slug' => 'view_ativos_descricoes_padrao_create',
                'group' => 'ativos',
            ],
            [
                'name' => 'Editar Descrições Padrão de Ativos',
                'slug' => 'view_ativos_descricoes_padrao_edit',
                'group' => 'ativos',
            ],
            [
                'name' => 'Excluir Descrições Padrão de Ativos',
                'slug' => 'view_ativos_descricoes_padrao_delete',
                'group' => 'ativos',
            ],
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permitems')
                ->where('slug', $permission['slug'])
                ->exists();

            if (!$exists) {
                DB::table('permitems')->insert($permission);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slugs = [
            'view_ativos_descricoes_padrao',
            'view_ativos_descricoes_padrao_create',
            'view_ativos_descricoes_padrao_edit',
            'view_ativos_descricoes_padrao_delete',
        ];

        foreach ($slugs as $slug) {
            $permission = DB::table('permitems')->where('slug', $slug)->first();
            if ($permission) {
                DB::table('permgroup_permitem')->where('permitem_id', $permission->id)->delete();
                DB::table('permitems')->where('slug', $slug)->delete();
            }
        }
    }
};

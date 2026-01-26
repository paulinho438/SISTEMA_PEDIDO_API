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
                'name' => 'Visualizar Responsáveis de Ativos',
                'slug' => 'view_ativos_responsaveis',
                'group' => 'ativos',
            ],
            [
                'name' => 'Criar Responsáveis de Ativos',
                'slug' => 'view_ativos_responsaveis_create',
                'group' => 'ativos',
            ],
            [
                'name' => 'Editar Responsáveis de Ativos',
                'slug' => 'view_ativos_responsaveis_edit',
                'group' => 'ativos',
            ],
            [
                'name' => 'Excluir Responsáveis de Ativos',
                'slug' => 'view_ativos_responsaveis_delete',
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
            'view_ativos_responsaveis',
            'view_ativos_responsaveis_create',
            'view_ativos_responsaveis_edit',
            'view_ativos_responsaveis_delete',
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

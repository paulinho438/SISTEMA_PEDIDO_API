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
        // Obter IDs das permissões que serão removidas
        $permissionSlugs = [
            // Categorias
            'view_categories',
            'view_categories_create',
            'view_categories_edit',
            'view_categories_delete',
            // Centro de Custo
            'view_costcenter',
            'view_costcenter_create',
            'view_costcenter_edit',
            'view_costcenter_delete',
            // Bancos
            'view_bancos',
            'view_bancos_create',
            'view_bancos_edit',
            'view_bancos_delete',
            // Clientes
            'view_clientes',
            'view_clientes_sensitive',
            'view_clientes_create',
            'view_clientes_edit',
            'view_clientes_delete'
        ];

        // Obter IDs das permissões
        $permissionIds = DB::table('permitems')
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->toArray();

        if (!empty($permissionIds)) {
            // Primeiro, remover os relacionamentos na tabela pivot
            DB::table('permgroup_permitem')
                ->whereIn('permitem_id', $permissionIds)
                ->delete();

            // Depois, remover as permissões
            DB::table('permitems')
                ->whereIn('id', $permissionIds)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reinserir permissões de Categorias
        DB::table('permitems')->insert([
            ['name' => 'Visualizar Categorias', 'slug' => 'view_categories', 'group' => 'categorias'],
            ['name' => 'Criar Categoria', 'slug' => 'view_categories_create', 'group' => 'categorias'],
            ['name' => 'Editar Categoria', 'slug' => 'view_categories_edit', 'group' => 'categorias'],
            ['name' => 'Excluir Categoria', 'slug' => 'view_categories_delete', 'group' => 'categorias'],
        ]);

        // Reinserir permissões de Centro de Custo
        DB::table('permitems')->insert([
            ['name' => 'Visualizar Centro de Custo', 'slug' => 'view_costcenter', 'group' => 'centrodecusto'],
            ['name' => 'Criar Centro de Custo', 'slug' => 'view_costcenter_create', 'group' => 'centrodecusto'],
            ['name' => 'Editar Centro de Custo', 'slug' => 'view_costcenter_edit', 'group' => 'centrodecusto'],
            ['name' => 'Excluir Centro de Custo', 'slug' => 'view_costcenter_delete', 'group' => 'centrodecusto'],
        ]);

        // Reinserir permissões de Bancos
        DB::table('permitems')->insert([
            ['name' => 'Visualizar Bancos', 'slug' => 'view_bancos', 'group' => 'bancos'],
            ['name' => 'Criar Bancos', 'slug' => 'view_bancos_create', 'group' => 'bancos'],
            ['name' => 'Editar Bancos', 'slug' => 'view_bancos_edit', 'group' => 'bancos'],
            ['name' => 'Excluir Bancos', 'slug' => 'view_bancos_delete', 'group' => 'bancos'],
        ]);

        // Reinserir permissões de Clientes
        DB::table('permitems')->insert([
            ['name' => 'Visualizar Clientes', 'slug' => 'view_clientes', 'group' => 'clientes'],
            ['name' => 'Visualizar Dados Sensiveis Cliente', 'slug' => 'view_clientes_sensitive', 'group' => 'clientes'],
            ['name' => 'Criar Cliente', 'slug' => 'view_clientes_create', 'group' => 'clientes'],
            ['name' => 'Editar Cliente', 'slug' => 'view_clientes_edit', 'group' => 'clientes'],
            ['name' => 'Excluir Cliente', 'slug' => 'view_clientes_delete', 'group' => 'clientes'],
        ]);
    }
};

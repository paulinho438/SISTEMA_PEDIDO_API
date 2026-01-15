<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseQuoteTestPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $companyId = 1; // GRUPO RIALMA

        // Buscar IDs dos grupos
        $groups = [
            'Supervisor de Compras' => null,
            'Comprador' => null,
            'Gerente Local' => null,
            'Engenheiro' => null,
            'Gerente Geral' => null,
            'Diretor' => null,
            'Presidente' => null,
        ];

        foreach ($groups as $groupName => $groupId) {
            $group = DB::table('permgroups')
                ->where('name', $groupName)
                ->where('company_id', $companyId)
                ->first();
            
            if ($group) {
                $groups[$groupName] = $group->id;
            }
        }

        // Buscar IDs das permissões
        $permissions = [
            'view_cotacoes' => null,
            'view_cotacoes_detail' => null,
            'create_cotacoes' => null,
            'edit_cotacoes' => null,
            'edit_cotacoes_detalhes' => null,
            'cotacoes_analisar_selecionar' => null,
            'cotacoes_assign_buyer' => null,
            'cotacoes_finalizar' => null,
            'cotacoes_aprovar_comprador' => null,
            'cotacoes_aprovar_gerente_local' => null,
            'cotacoes_aprovar_engenheiro' => null,
            'cotacoes_aprovar_gerente_geral' => null,
            'cotacoes_aprovar_diretor' => null,
            'cotacoes_aprovar_presidente' => null,
            'cotacoes_imprimir' => null,
            'view_dashboard' => null,
        ];

        foreach ($permissions as $permissionSlug => $permissionId) {
            $permission = DB::table('permitems')
                ->where('slug', $permissionSlug)
                ->first();
            
            if ($permission) {
                $permissions[$permissionSlug] = $permission->id;
            }
        }

        // Mapear permissões por grupo
        $groupPermissions = [
            'Supervisor de Compras' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'create_cotacoes',
                'cotacoes_analisar_selecionar',
                'cotacoes_assign_buyer',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
            'Comprador' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'edit_cotacoes_detalhes',
                'cotacoes_finalizar',
                'cotacoes_aprovar_comprador',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
            'Gerente Local' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'cotacoes_aprovar_gerente_local',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
            'Engenheiro' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'cotacoes_aprovar_engenheiro',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
            'Gerente Geral' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'cotacoes_aprovar_gerente_geral',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
            'Diretor' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'cotacoes_aprovar_diretor',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
            'Presidente' => [
                'view_cotacoes',
                'view_cotacoes_detail',
                'cotacoes_aprovar_presidente',
                'cotacoes_imprimir',
                'view_dashboard',
            ],
        ];

        // Associar permissões aos grupos
        foreach ($groupPermissions as $groupName => $permissionSlugs) {
            if (!isset($groups[$groupName]) || !$groups[$groupName]) {
                $this->command->warn("Grupo '{$groupName}' não encontrado, pulando...");
                continue;
            }

            $groupId = $groups[$groupName];

            foreach ($permissionSlugs as $permissionSlug) {
                if (!isset($permissions[$permissionSlug]) || !$permissions[$permissionSlug]) {
                    $this->command->warn("Permissão '{$permissionSlug}' não encontrada, pulando...");
                    continue;
                }

                $permissionId = $permissions[$permissionSlug];

                // Verificar se já existe
                $exists = DB::table('permgroup_permitem')
                    ->where('permgroup_id', $groupId)
                    ->where('permitem_id', $permissionId)
                    ->exists();

                if (!$exists) {
                    DB::table('permgroup_permitem')->insert([
                        'permgroup_id' => $groupId,
                        'permitem_id' => $permissionId,
                    ]);
                }
            }
        }

        $this->command->info('Permissões associadas aos grupos com sucesso!');
    }
}


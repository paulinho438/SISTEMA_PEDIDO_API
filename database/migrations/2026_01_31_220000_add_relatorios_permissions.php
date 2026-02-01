<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permissões específicas para cada relatório do menu Relatórios.
     */
    private array $permissions = [
        [
            'name' => 'Visualizar Relatório Custos por Centro de Custo',
            'slug' => 'view_relatorio_custos_centro_custo',
            'group' => 'relatorios',
        ],
        [
            'name' => 'Visualizar Relatório de Cotação',
            'slug' => 'view_relatorio_cotacao',
            'group' => 'relatorios',
        ],
        [
            'name' => 'Visualizar Relatório Custos por Fornecedor',
            'slug' => 'view_relatorio_custos_fornecedor',
            'group' => 'relatorios',
        ],
        [
            'name' => 'Visualizar Relatório Custos por Solicitação',
            'slug' => 'view_relatorio_custos_solicitacao',
            'group' => 'relatorios',
        ],
        [
            'name' => 'Visualizar Relatório Histórico por Período',
            'slug' => 'view_relatorio_historico_periodo',
            'group' => 'relatorios',
        ],
        [
            'name' => 'Visualizar Relatório Solicitação / Produto',
            'slug' => 'view_relatorio_solicitacao_produto',
            'group' => 'relatorios',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $perm) {
            $exists = DB::table('permitems')->where('slug', $perm['slug'])->exists();
            if (!$exists) {
                DB::table('permitems')->insert($perm);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->permissions as $perm) {
            $row = DB::table('permitems')->where('slug', $perm['slug'])->first();
            if ($row) {
                DB::table('permgroup_permitem')->where('permitem_id', $row->id)->delete();
                DB::table('permitems')->where('slug', $perm['slug'])->delete();
            }
        }
    }
};

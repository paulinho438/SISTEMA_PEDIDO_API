<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetAuxiliarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $companyId = 1; // GRUPO RIALMA

        // Condições de Uso
        $useConditions = [
            ['code' => 'NOVO', 'name' => 'Novo', 'description' => 'Item novo, sem uso', 'active' => true],
            ['code' => 'USADO', 'name' => 'Usado', 'description' => 'Item em uso, bom estado', 'active' => true],
            ['code' => 'REMOVIDO', 'name' => 'Removido', 'description' => 'Item removido ou desmontado', 'active' => true],
            ['code' => 'MANUTENCAO', 'name' => 'Em Manutenção', 'description' => 'Item em manutenção', 'active' => true],
            ['code' => 'OBSOLETO', 'name' => 'Obsoleto', 'description' => 'Item obsoleto', 'active' => true],
            ['code' => 'DESCARTE', 'name' => 'Descarte', 'description' => 'Item para descarte', 'active' => true],
            ['code' => 'DANIFICADO', 'name' => 'Danificado', 'description' => 'Item danificado', 'active' => true],
        ];

        $useConditionIds = [];
        foreach ($useConditions as $item) {
            $existing = DB::table('asset_use_conditions')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $useConditionIds[] = $existing->id;
            } else {
                $id = DB::table('asset_use_conditions')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $useConditionIds[] = $id;
            }
        }

        // Descrições Padrão
        $standardDescriptions = [
            ['code' => 'NOTEBOOK', 'name' => 'Notebook', 'description' => 'Computador portátil', 'active' => true],
            ['code' => 'MONITOR', 'name' => 'Monitor', 'description' => 'Monitor de vídeo', 'active' => true],
            ['code' => 'MOUSE', 'name' => 'Mouse', 'description' => 'Mouse para computador', 'active' => true],
            ['code' => 'TECLADO', 'name' => 'Teclado', 'description' => 'Teclado para computador', 'active' => true],
            ['code' => 'CADEIRA', 'name' => 'Cadeira', 'description' => 'Cadeira ergonômica', 'active' => true],
            ['code' => 'MESA', 'name' => 'Mesa', 'description' => 'Mesa de escritório', 'active' => true],
            ['code' => 'IMPRESSORA', 'name' => 'Impressora', 'description' => 'Impressora multifuncional', 'active' => true],
            ['code' => 'VEICULO', 'name' => 'Veículo', 'description' => 'Veículo automotor', 'active' => true],
        ];

        $standardDescriptionIds = [];
        foreach ($standardDescriptions as $item) {
            $existing = DB::table('asset_standard_descriptions')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $standardDescriptionIds[] = $existing->id;
            } else {
                $id = DB::table('asset_standard_descriptions')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $standardDescriptionIds[] = $id;
            }
        }

        // Filiais
        $branches = [
            ['code' => 'MATRIZ', 'name' => 'Matriz - Brasília', 'address' => 'Brasília - DF', 'active' => true],
            ['code' => 'FIL-01', 'name' => 'Filial 01 - Goiânia', 'address' => 'Goiânia - GO', 'active' => true],
            ['code' => 'FIL-02', 'name' => 'Filial 02 - Belo Horizonte', 'address' => 'Belo Horizonte - MG', 'active' => true],
            ['code' => 'FIL-03', 'name' => 'Filial 03 - São Paulo', 'address' => 'São Paulo - SP', 'active' => true],
        ];

        $branchIds = [];
        foreach ($branches as $item) {
            $existing = DB::table('asset_branches')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $branchIds[] = $existing->id;
            } else {
                $id = DB::table('asset_branches')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $branchIds[] = $id;
            }
        }

        // Sub Tipos 1
        $subTypes1 = [
            ['code' => 'INFO', 'name' => 'Informática', 'description' => 'Equipamentos de informática', 'active' => true],
            ['code' => 'MOB', 'name' => 'Mobiliário', 'description' => 'Móveis e mobiliário', 'active' => true],
            ['code' => 'VEIC', 'name' => 'Veículos', 'description' => 'Veículos automotores', 'active' => true],
            ['code' => 'ELET', 'name' => 'Eletrodomésticos', 'description' => 'Eletrodomésticos', 'active' => true],
        ];

        $subType1Ids = [];
        foreach ($subTypes1 as $item) {
            $existing = DB::table('asset_sub_types_1')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $subType1Ids[] = $existing->id;
            } else {
                $id = DB::table('asset_sub_types_1')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $subType1Ids[] = $id;
            }
        }

        // Sub Tipos 2
        $subTypes2 = [
            ['code' => 'NOTE', 'name' => 'Notebooks', 'asset_sub_type_1_id' => $subType1Ids[0], 'description' => 'Notebooks e laptops', 'active' => true],
            ['code' => 'DESK', 'name' => 'Desktops', 'asset_sub_type_1_id' => $subType1Ids[0], 'description' => 'Computadores desktop', 'active' => true],
            ['code' => 'PERI', 'name' => 'Periféricos', 'asset_sub_type_1_id' => $subType1Ids[0], 'description' => 'Periféricos de informática', 'active' => true],
            ['code' => 'CADE', 'name' => 'Cadeiras', 'asset_sub_type_1_id' => $subType1Ids[1], 'description' => 'Cadeiras e assentos', 'active' => true],
            ['code' => 'MESA', 'name' => 'Mesas', 'asset_sub_type_1_id' => $subType1Ids[1], 'description' => 'Mesas de escritório', 'active' => true],
            ['code' => 'AUTO', 'name' => 'Automóveis', 'asset_sub_type_1_id' => $subType1Ids[2], 'description' => 'Automóveis de passeio', 'active' => true],
        ];

        $subType2Ids = [];
        foreach ($subTypes2 as $item) {
            $existing = DB::table('asset_sub_types_2')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $subType2Ids[] = $existing->id;
            } else {
                $id = DB::table('asset_sub_types_2')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $subType2Ids[] = $id;
            }
        }

        // Agrupamentos
        $groupings = [
            ['code' => 'ADM', 'name' => 'Administrativo', 'description' => 'Ativos administrativos', 'active' => true],
            ['code' => 'PROD', 'name' => 'Produção', 'description' => 'Ativos de produção', 'active' => true],
            ['code' => 'TI', 'name' => 'Tecnologia da Informação', 'description' => 'Ativos de TI', 'active' => true],
            ['code' => 'VEND', 'name' => 'Vendas', 'description' => 'Ativos de vendas', 'active' => true],
        ];

        $groupingIds = [];
        foreach ($groupings as $item) {
            $existing = DB::table('asset_groupings')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $groupingIds[] = $existing->id;
            } else {
                $id = DB::table('asset_groupings')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $groupingIds[] = $id;
            }
        }

        // Contas
        $accounts = [
            ['code' => '1.1.01', 'name' => 'Imobilizado - Informática', 'type' => 'Ativo', 'description' => 'Conta de imobilizado para informática', 'active' => true],
            ['code' => '1.1.02', 'name' => 'Imobilizado - Mobiliário', 'type' => 'Ativo', 'description' => 'Conta de imobilizado para mobiliário', 'active' => true],
            ['code' => '1.1.03', 'name' => 'Imobilizado - Veículos', 'type' => 'Ativo', 'description' => 'Conta de imobilizado para veículos', 'active' => true],
        ];

        $accountIds = [];
        foreach ($accounts as $item) {
            $existing = DB::table('asset_accounts')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $accountIds[] = $existing->id;
            } else {
                $id = DB::table('asset_accounts')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $accountIds[] = $id;
            }
        }

        // Projetos
        $projects = [
            ['code' => 'PROJ-001', 'name' => 'Expansão Filial Goiânia', 'description' => 'Projeto de expansão da filial de Goiânia', 'start_date' => $now->copy()->subMonths(3)->format('Y-m-d'), 'end_date' => $now->copy()->addMonths(6)->format('Y-m-d'), 'active' => true],
            ['code' => 'PROJ-002', 'name' => 'Modernização TI', 'description' => 'Projeto de modernização da infraestrutura de TI', 'start_date' => $now->copy()->subMonths(1)->format('Y-m-d'), 'end_date' => $now->copy()->addMonths(5)->format('Y-m-d'), 'active' => true],
            ['code' => 'PROJ-003', 'name' => 'Renovação Mobiliário', 'description' => 'Projeto de renovação do mobiliário', 'start_date' => $now->copy()->subMonths(2)->format('Y-m-d'), 'end_date' => $now->copy()->addMonths(4)->format('Y-m-d'), 'active' => true],
        ];

        $projectIds = [];
        foreach ($projects as $item) {
            $existing = DB::table('asset_projects')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $projectIds[] = $existing->id;
            } else {
                $id = DB::table('asset_projects')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $projectIds[] = $id;
            }
        }

        // Unidades de Negócio
        $businessUnits = [
            ['code' => 'UN-001', 'name' => 'Unidade Operacional', 'description' => 'Unidade de negócio operacional', 'active' => true],
            ['code' => 'UN-002', 'name' => 'Unidade Administrativa', 'description' => 'Unidade de negócio administrativa', 'active' => true],
            ['code' => 'UN-003', 'name' => 'Unidade Comercial', 'description' => 'Unidade de negócio comercial', 'active' => true],
        ];

        $businessUnitIds = [];
        foreach ($businessUnits as $item) {
            $existing = DB::table('asset_business_units')
                ->where('code', $item['code'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $businessUnitIds[] = $existing->id;
            } else {
                $id = DB::table('asset_business_units')->insertGetId([
                    ...$item,
                    'company_id' => $companyId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $businessUnitIds[] = $id;
            }
        }

        $this->command->info('Seed de Cadastros Auxiliares de Ativos criado com sucesso!');
        $this->command->info('Condições de Uso: ' . count($useConditions));
        $this->command->info('Descrições Padrão: ' . count($standardDescriptions));
        $this->command->info('Filiais: ' . count($branches));
        $this->command->info('Sub Tipos 1: ' . count($subTypes1));
        $this->command->info('Sub Tipos 2: ' . count($subTypes2));
        $this->command->info('Agrupamentos: ' . count($groupings));
        $this->command->info('Contas: ' . count($accounts));
        $this->command->info('Projetos: ' . count($projects));
        $this->command->info('Unidades de Negócio: ' . count($businessUnits));
    }
}


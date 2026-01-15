<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $companyId = 1; // GRUPO RIALMA

        // Buscar dados auxiliares criados anteriormente
        $branches = DB::table('asset_branches')->where('company_id', $companyId)->get();
        $locations = DB::table('stock_locations')->where('company_id', $companyId)->get();
        $useConditions = DB::table('asset_use_conditions')->where('company_id', $companyId)->get();
        $standardDescriptions = DB::table('asset_standard_descriptions')->where('company_id', $companyId)->get();
        $subTypes1 = DB::table('asset_sub_types_1')->where('company_id', $companyId)->get();
        $subTypes2 = DB::table('asset_sub_types_2')->where('company_id', $companyId)->get();
        $accounts = DB::table('asset_accounts')->where('company_id', $companyId)->get();
        $projects = DB::table('asset_projects')->where('company_id', $companyId)->get();
        $businessUnits = DB::table('asset_business_units')->where('company_id', $companyId)->get();
        $groupings = DB::table('asset_groupings')->where('company_id', $companyId)->get();

        if ($branches->isEmpty() || $locations->isEmpty()) {
            $this->command->warn('Execute primeiro o AssetAuxiliarySeeder!');
            return;
        }

        // Definir ativos para criação
        $assetsData = [
            [
                'asset_number' => 'ATIVO-00001',
                'increment' => 1,
                'acquisition_date' => $now->copy()->subMonths(12)->format('Y-m-d'),
                'status' => 'incluido',
                'description' => 'Notebook Dell Inspiron 15 3000, Intel Core i5, 8GB RAM, 256GB SSD',
                'brand' => 'Dell',
                'model' => 'Inspiron 15 3000',
                'serial_number' => 'DL-2023-001234',
                'tag' => 'TAG-001',
                'use_condition_id' => $useConditions->where('code', 'USADO')->first()?->id,
                'standard_description_id' => $standardDescriptions->where('code', 'NOTEBOOK')->first()?->id,
                'sub_type_1_id' => $subTypes1->where('code', 'INFO')->first()?->id,
                'sub_type_2_id' => $subTypes2->where('code', 'NOTE')->first()?->id,
                'branch_id' => $branches->where('code', 'MATRIZ')->first()?->id,
                'location_id' => $locations->where('code', 'MATRIZ')->first()?->id,
                'account_id' => $accounts->where('code', '1.1.01')->first()?->id,
                'project_id' => $projects->where('code', 'PROJ-002')->first()?->id,
                'business_unit_id' => $businessUnits->where('code', 'UN-002')->first()?->id,
                'grouping_id' => $groupings->where('code', 'TI')->first()?->id,
                'value_brl' => 3500.00,
                'value_usd' => null,
                'responsible_id' => 1, // Admin
                'manufacture_year' => 2023,
                'observation' => 'Notebook adquirido para equipe de TI',
            ],
            [
                'asset_number' => 'ATIVO-00002',
                'increment' => 2,
                'acquisition_date' => $now->copy()->subMonths(10)->format('Y-m-d'),
                'status' => 'incluido',
                'description' => 'Monitor LG UltraWide 29" Full HD IPS',
                'brand' => 'LG',
                'model' => '29WL500-B',
                'serial_number' => 'LG-2023-005678',
                'tag' => 'TAG-002',
                'use_condition_id' => $useConditions->where('code', 'USADO')->first()?->id,
                'standard_description_id' => $standardDescriptions->where('code', 'MONITOR')->first()?->id,
                'sub_type_1_id' => $subTypes1->where('code', 'INFO')->first()?->id,
                'sub_type_2_id' => $subTypes2->where('code', 'PERI')->first()?->id,
                'branch_id' => $branches->where('code', 'MATRIZ')->first()?->id,
                'location_id' => $locations->where('code', 'MATRIZ')->first()?->id,
                'account_id' => $accounts->where('code', '1.1.01')->first()?->id,
                'value_brl' => 1200.00,
                'value_usd' => null,
                'responsible_id' => 1,
                'manufacture_year' => 2023,
                'observation' => 'Monitor para estação de trabalho',
            ],
            [
                'asset_number' => 'ATIVO-00003',
                'increment' => 3,
                'acquisition_date' => $now->copy()->subMonths(8)->format('Y-m-d'),
                'status' => 'incluido',
                'description' => 'Cadeira Ergonômica Executiva com Apoio Lombar',
                'brand' => 'Flexform',
                'model' => 'Excellence',
                'serial_number' => 'FF-2023-009012',
                'tag' => 'TAG-003',
                'use_condition_id' => $useConditions->where('code', 'USADO')->first()?->id,
                'standard_description_id' => $standardDescriptions->where('code', 'CADEIRA')->first()?->id,
                'sub_type_1_id' => $subTypes1->where('code', 'MOB')->first()?->id,
                'sub_type_2_id' => $subTypes2->where('code', 'CADE')->first()?->id,
                'branch_id' => $branches->where('code', 'FIL-01')->first()?->id,
                'location_id' => $locations->where('code', 'FILIAL-01')->first()?->id,
                'account_id' => $accounts->where('code', '1.1.02')->first()?->id,
                'value_brl' => 850.00,
                'value_usd' => null,
                'responsible_id' => 1,
                'manufacture_year' => 2023,
                'observation' => 'Cadeira para sala executiva',
            ],
            [
                'asset_number' => 'ATIVO-00004',
                'increment' => 4,
                'acquisition_date' => $now->copy()->subMonths(6)->format('Y-m-d'),
                'status' => 'incluido',
                'description' => 'Mouse Logitech MX Master 3, Sem Fio',
                'brand' => 'Logitech',
                'model' => 'MX Master 3',
                'serial_number' => 'LOG-2023-012345',
                'tag' => 'TAG-004',
                'use_condition_id' => $useConditions->where('code', 'NOVO')->first()?->id,
                'standard_description_id' => $standardDescriptions->where('code', 'MOUSE')->first()?->id,
                'sub_type_1_id' => $subTypes1->where('code', 'INFO')->first()?->id,
                'sub_type_2_id' => $subTypes2->where('code', 'PERI')->first()?->id,
                'branch_id' => $branches->where('code', 'MATRIZ')->first()?->id,
                'location_id' => $locations->where('code', 'MATRIZ')->first()?->id,
                'account_id' => $accounts->where('code', '1.1.01')->first()?->id,
                'value_brl' => 450.00,
                'value_usd' => null,
                'responsible_id' => 1,
                'manufacture_year' => 2023,
                'observation' => 'Mouse para uso profissional',
            ],
            [
                'asset_number' => 'ATIVO-00005',
                'increment' => 5,
                'acquisition_date' => $now->copy()->subMonths(4)->format('Y-m-d'),
                'status' => 'incluido',
                'description' => 'Impressora Multifuncional HP LaserJet Pro',
                'brand' => 'HP',
                'model' => 'LaserJet Pro M404dn',
                'serial_number' => 'HP-2023-015678',
                'tag' => 'TAG-005',
                'use_condition_id' => $useConditions->where('code', 'USADO')->first()?->id,
                'standard_description_id' => $standardDescriptions->where('code', 'IMPRESSORA')->first()?->id,
                'sub_type_1_id' => $subTypes1->where('code', 'INFO')->first()?->id,
                'sub_type_2_id' => $subTypes2->where('code', 'PERI')->first()?->id,
                'branch_id' => $branches->where('code', 'FIL-02')->first()?->id,
                'location_id' => $locations->where('code', 'FILIAL-02')->first()?->id,
                'account_id' => $accounts->where('code', '1.1.01')->first()?->id,
                'value_brl' => 2800.00,
                'value_usd' => null,
                'responsible_id' => 1,
                'manufacture_year' => 2023,
                'observation' => 'Impressora para escritório',
            ],
        ];

        $assetIds = [];
        foreach ($assetsData as $assetData) {
            $existing = DB::table('assets')
                ->where('asset_number', $assetData['asset_number'])
                ->where('company_id', $companyId)
                ->first();
            
            if ($existing) {
                $assetIds[] = $existing->id;
                continue;
            }
            
            $id = DB::table('assets')->insertGetId([
                ...$assetData,
                'company_id' => $companyId,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $assetIds[] = $id;

            // Criar movimentação de cadastro para cada ativo (se não existir)
            $movementExists = DB::table('asset_movements')
                ->where('asset_id', $id)
                ->where('movement_type', 'cadastro')
                ->exists();
            
            if (!$movementExists) {
                DB::table('asset_movements')->insert([
                'asset_id' => $id,
                'movement_type' => 'cadastro',
                'movement_date' => $assetData['acquisition_date'],
                'from_branch_id' => null,
                'to_branch_id' => $assetData['branch_id'],
                'from_location_id' => null,
                'to_location_id' => $assetData['location_id'],
                'from_responsible_id' => null,
                'to_responsible_id' => $assetData['responsible_id'],
                'from_cost_center_id' => null,
                'to_cost_center_id' => null,
                'observation' => 'Cadastro inicial do ativo via seeder',
                'user_id' => 1,
                'reference_type' => null,
                'reference_id' => null,
                'reference_number' => null,
                'created_at' => $now,
                'updated_at' => $now,
                ]);
            }
        }

        // Criar algumas movimentações adicionais (transferências)
        if (count($assetIds) >= 2) {
            // Transferir primeiro ativo para outra filial
            DB::table('asset_movements')->insert([
                'asset_id' => $assetIds[0],
                'movement_type' => 'transferencia',
                'movement_date' => $now->copy()->subMonths(2)->format('Y-m-d'),
                'from_branch_id' => $branches->where('code', 'MATRIZ')->first()?->id,
                'to_branch_id' => $branches->where('code', 'FIL-01')->first()?->id,
                'from_location_id' => $locations->where('code', 'MATRIZ')->first()?->id,
                'to_location_id' => $locations->where('code', 'FILIAL-01')->first()?->id,
                'from_responsible_id' => 1,
                'to_responsible_id' => 1,
                'observation' => 'Transferência de ativo entre filiais',
                'user_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Atualizar ativo transferido
            DB::table('assets')
                ->where('id', $assetIds[0])
                ->update([
                    'branch_id' => $branches->where('code', 'FIL-01')->first()?->id,
                    'location_id' => $locations->where('code', 'FILIAL-01')->first()?->id,
                    'status' => 'transferido',
                    'updated_at' => $now,
                ]);
        }

        $this->command->info('Seed de Ativos criado com sucesso!');
        $this->command->info('Ativos criados: ' . count($assetsData));
        $this->command->info('Movimentações criadas: ' . (count($assetsData) + 1));
    }
}


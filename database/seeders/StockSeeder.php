<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $companyId = 1; // GRUPO RIALMA

        // Criar Locais de Estoque
        $locations = [
            [
                'code' => 'MATRIZ',
                'name' => 'Matriz - Brasília',
                'address' => 'Brasília - DF',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'FILIAL-01',
                'name' => 'Filial 01 - Goiânia',
                'address' => 'Goiânia - GO',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'FILIAL-02',
                'name' => 'Filial 02 - Belo Horizonte',
                'address' => 'Belo Horizonte - MG',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'DEPOSITO',
                'name' => 'Depósito Central',
                'address' => 'Brasília - DF',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $locationIds = [];
        foreach ($locations as $location) {
            $existing = DB::table('stock_locations')
                ->where('code', $location['code'])
                ->where('company_id', $location['company_id'])
                ->first();
            
            if ($existing) {
                $locationIds[] = $existing->id;
            } else {
                $id = DB::table('stock_locations')->insertGetId($location);
                $locationIds[] = $id;
            }
        }

        // Criar Produtos de Estoque
        $products = [
            [
                'code' => 'PROD-001',
                'reference' => 'DELL-INS-15-001',
                'description' => 'Notebook Dell Inspiron 15, Intel Core i5, 8GB RAM, 256GB SSD',
                'unit' => 'UN',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-002',
                'reference' => 'LOG-MX-M3',
                'description' => 'Mouse sem fio Logitech MX Master 3, recarregável',
                'unit' => 'UN',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-003',
                'reference' => 'HYPERX-ALLOY-FPS',
                'description' => 'Teclado mecânico HyperX Alloy FPS RGB',
                'unit' => 'UN',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-004',
                'reference' => 'LG-29WL500',
                'description' => 'Monitor LG 29" UltraWide Full HD IPS',
                'unit' => 'UN',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-005',
                'reference' => 'HDMI-2.0-2M',
                'description' => 'Cabo HDMI 2.0 2 metros, alta velocidade',
                'unit' => 'UN',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-006',
                'reference' => 'PAPEL-A4-75G',
                'description' => 'Resma de papel A4 75g, 500 folhas',
                'unit' => 'CX',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-007',
                'reference' => 'CANETA-AZUL-CX',
                'description' => 'Caneta esferográfica azul, ponta média',
                'unit' => 'CX',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'PROD-008',
                'reference' => 'CADEIRA-ERG-EXEC',
                'description' => 'Cadeira ergonômica executiva com apoio lombar',
                'unit' => 'UN',
                'active' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $productIds = [];
        foreach ($products as $product) {
            $existing = DB::table('stock_products')
                ->where('code', $product['code'])
                ->where('company_id', $product['company_id'])
                ->first();
            
            if ($existing) {
                $productIds[] = $existing->id;
            } else {
                $id = DB::table('stock_products')->insertGetId($product);
                $productIds[] = $id;
            }
        }

        // Criar Estoque (combinação produto + local)
        $stocks = [];
        foreach ($productIds as $productId) {
            foreach ($locationIds as $locationId) {
                $existing = DB::table('stocks')
                    ->where('stock_product_id', $productId)
                    ->where('stock_location_id', $locationId)
                    ->where('company_id', $companyId)
                    ->first();
                
                if (!$existing) {
                    $quantity = (float) rand(0, 100);
                    $stocks[] = [
                        'stock_product_id' => $productId,
                        'stock_location_id' => $locationId,
                        'quantity_total' => $quantity,
                        'quantity_available' => $quantity,
                        'quantity_reserved' => 0.0,
                        'company_id' => $companyId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (!empty($stocks)) {
            DB::table('stocks')->insert($stocks);
        }

        // Criar algumas movimentações de exemplo
        $movements = [];
        $movementTypes = ['entrada', 'saida', 'ajuste'];
        
        for ($i = 0; $i < 20; $i++) {
            $productId = $productIds[array_rand($productIds)];
            $locationId = $locationIds[array_rand($locationIds)];
            $movementType = $movementTypes[array_rand($movementTypes)];
            $quantity = (float) rand(1, 50);
            
            // Buscar stock correspondente
            $stock = DB::table('stocks')
                ->where('stock_product_id', $productId)
                ->where('stock_location_id', $locationId)
                ->first();
            
            if ($stock) {
                $quantityBefore = (float) $stock->quantity_available;
                $quantityAfter = $movementType === 'entrada' 
                    ? $quantityBefore + $quantity 
                    : ($movementType === 'saida' 
                        ? max(0.0, $quantityBefore - $quantity) 
                        : $quantity);
                
                $cost = (float) (rand(1000, 50000) / 100);
                $totalCost = (float) ($quantity * rand(1000, 50000) / 100);
                
                $movements[] = [
                    'stock_id' => (int) $stock->id,
                    'stock_product_id' => (int) $productId,
                    'stock_location_id' => (int) $locationId,
                    'movement_type' => $movementType,
                    'quantity' => $quantity,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'reference_type' => 'ajuste_manual',
                    'reference_id' => null,
                    'reference_number' => 'AJ-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                    'cost' => $cost,
                    'total_cost' => $totalCost,
                    'observation' => 'Movimentação de teste gerada por seeder',
                    'user_id' => 1,
                    'company_id' => $companyId,
                    'movement_date' => $now->copy()->subDays(rand(0, 30))->format('Y-m-d'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                
                // Atualizar estoque
                DB::table('stocks')
                    ->where('id', $stock->id)
                    ->update([
                        'quantity_available' => $quantityAfter,
                        'quantity_total' => $quantityAfter,
                        'updated_at' => $now,
                    ]);
            }
        }

        if (!empty($movements)) {
            DB::table('stock_movements')->insert($movements);
        }

        $this->command->info('Seed de Estoque criado com sucesso!');
        $this->command->info('Locais criados: ' . count($locations));
        $this->command->info('Produtos criados: ' . count($products));
        $this->command->info('Estoques criados: ' . count($stocks));
        $this->command->info('Movimentações criadas: ' . count($movements));
    }
}


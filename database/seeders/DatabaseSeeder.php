<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class,
            CompanySeeder::class,
            CompanyUserSeeder::class,

                // Cria as tabelas base
            PermGroupsSeeder::class,
            PermItemsSeeder::class,

                // Só agora faz o vínculo
            PermLinksSeeder::class,
            PermLinksUserSeeder::class,
            
            // Seeders de Estoque e Ativos
            StockPermissionsSeeder::class,
            StockSeeder::class,
            AssetAuxiliarySeeder::class,
            AssetSeeder::class,

        ]);
    }
}

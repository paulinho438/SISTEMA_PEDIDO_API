<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permitem;
use Illuminate\Support\Facades\DB;

class StockPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Almoxarife',
                'slug' => 'almoxarife',
                'group' => 'Estoque',
            ],
            [
                'name' => 'Supervisor de Estoque',
                'slug' => 'supervisor_estoque',
                'group' => 'Estoque',
            ],
        ];

        foreach ($permissions as $permission) {
            $exists = Permitem::where('slug', $permission['slug'])->exists();
            
            if (!$exists) {
                Permitem::create($permission);
            }
        }
    }
}


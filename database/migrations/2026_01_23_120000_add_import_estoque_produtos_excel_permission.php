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
        $permissionExists = DB::table('permitems')
            ->where('slug', 'import_estoque_produtos_excel')
            ->exists();

        if (!$permissionExists) {
            DB::table('permitems')->insert([
                'name' => 'Importar Produtos via Excel',
                'slug' => 'import_estoque_produtos_excel',
                'group' => 'estoque',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = DB::table('permitems')->where('slug', 'import_estoque_produtos_excel')->first();
        
        if ($permission) {
            // Remover associações com grupos
            DB::table('permgroup_permitem')->where('permitem_id', $permission->id)->delete();
            // Remover a permissão
            DB::table('permitems')->where('slug', 'import_estoque_produtos_excel')->delete();
        }
    }
};


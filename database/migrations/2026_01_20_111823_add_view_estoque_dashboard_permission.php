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
        // Verificar se a permissão já existe
        $permissionExists = DB::table('permitems')
            ->where('slug', 'view_estoque_dashboard')
            ->exists();

        if (!$permissionExists) {
            // Inserir a nova permissão
            DB::table('permitems')->insert([
                'name' => 'Visualizar Dashboard de Estoque',
                'slug' => 'view_estoque_dashboard',
                'group' => 'estoque',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover permissões da tabela pivot primeiro
        $permission = DB::table('permitems')->where('slug', 'view_estoque_dashboard')->first();
        if ($permission) {
            DB::table('permgroup_permitem')->where('permitem_id', $permission->id)->delete();
            DB::table('permitems')->where('slug', 'view_estoque_dashboard')->delete();
        }
    }
};

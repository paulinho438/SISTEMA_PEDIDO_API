<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permissão para visualizar a tela de auditoria.
     */
    public function up(): void
    {
        $exists = DB::table('permitems')->where('slug', 'view_auditoria')->exists();
        if (!$exists) {
            DB::table('permitems')->insert([
                'name' => 'Visualizar Auditoria',
                'slug' => 'view_auditoria',
                'group' => 'auditoria',
            ]);
        }
    }

    public function down(): void
    {
        $row = DB::table('permitems')->where('slug', 'view_auditoria')->first();
        if ($row) {
            DB::table('permgroup_permitem')->where('permitem_id', $row->id)->delete();
            DB::table('permitems')->where('slug', 'view_auditoria')->delete();
        }
    }
};

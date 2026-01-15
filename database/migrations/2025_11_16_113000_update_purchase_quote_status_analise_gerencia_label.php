<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analise_gerencia')
            ->update([
                'label' => 'Analis. / Ger.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analise_gerencia')
            ->update([
                'label' => 'Análise Gerência',
                'updated_at' => now(),
            ]);
    }
};


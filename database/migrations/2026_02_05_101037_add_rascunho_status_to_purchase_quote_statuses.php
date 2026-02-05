<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Inserir status "rascunho" antes de "aguardando" (order 0)
        DB::table('purchase_quote_statuses')->insert([
            [
                'slug' => 'rascunho',
                'label' => 'Rascunho',
                'description' => 'Solicitação salva como rascunho. Visível apenas para quem criou.',
                'required_profile' => 'Colaborador',
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Atualizar ordem dos demais status (incrementar order em 1)
        DB::table('purchase_quote_statuses')
            ->where('slug', 'aguardando')
            ->update(['order' => 1]);
        
        DB::table('purchase_quote_statuses')
            ->where('slug', 'em_analise_supervisor')
            ->update(['order' => 2]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'autorizado')
            ->update(['order' => 3]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'cotacao')
            ->update(['order' => 4]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'compra_em_andamento')
            ->update(['order' => 5]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'finalizada')
            ->update(['order' => 6]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analisada')
            ->update(['order' => 7]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analisada_aguardando')
            ->update(['order' => 8]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analise_gerencia')
            ->update(['order' => 9]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'aprovado')
            ->update(['order' => 10]);
    }

    public function down(): void
    {
        // Reverter ordem dos status
        DB::table('purchase_quote_statuses')
            ->where('slug', 'aguardando')
            ->update(['order' => 1]);
        
        DB::table('purchase_quote_statuses')
            ->where('slug', 'em_analise_supervisor')
            ->update(['order' => 2]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'autorizado')
            ->update(['order' => 2]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'cotacao')
            ->update(['order' => 3]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'compra_em_andamento')
            ->update(['order' => 4]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'finalizada')
            ->update(['order' => 5]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analisada')
            ->update(['order' => 6]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analisada_aguardando')
            ->update(['order' => 7]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'analise_gerencia')
            ->update(['order' => 8]);
            
        DB::table('purchase_quote_statuses')
            ->where('slug', 'aprovado')
            ->update(['order' => 9]);

        // Remover status rascunho
        DB::table('purchase_quote_statuses')
            ->where('slug', 'rascunho')
            ->delete();
    }
};

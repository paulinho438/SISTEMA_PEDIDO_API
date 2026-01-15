<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Inserir novo status entre "aguardando" e "autorizado"
        DB::table('purchase_quote_statuses')->insert([
            [
                'slug' => 'em_analise_supervisor',
                'label' => 'Em Análise - Supervisor',
                'description' => 'Supervisor de compras analisando e definindo níveis de aprovação',
                'required_profile' => 'Sup. Compras',
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Atualizar ordem dos status seguintes
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remover o status
        DB::table('purchase_quote_statuses')
            ->where('slug', 'em_analise_supervisor')
            ->delete();

        // Reverter ordem dos status
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
    }
};

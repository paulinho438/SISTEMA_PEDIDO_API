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
        // Verificar se o status já existe
        $exists = DB::table('purchase_quote_statuses')
            ->where('slug', 'reprovado')
            ->exists();

        if (!$exists) {
            // Inserir novo status "reprovado"
            DB::table('purchase_quote_statuses')->insert([
                [
                    'slug' => 'reprovado',
                    'label' => 'Reprovado',
                    'description' => 'Solicitação reprovada e pode ser alterada.',
                    'required_profile' => null,
                    'order' => 99, // Ordem alta para não interferir no fluxo normal
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
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
            ->where('slug', 'reprovado')
            ->delete();
    }
};


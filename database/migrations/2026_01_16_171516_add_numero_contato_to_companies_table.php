<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        // Verificar se a coluna já existe (caso tenha sido criada manualmente)
        if (!Schema::hasColumn('companies', 'numero_contato')) {
            Schema::table('companies', function (Blueprint $table) {
                // Adicionar numero_contato após credito_simples_nacional ou cnpj
                if (Schema::hasColumn('companies', 'credito_simples_nacional')) {
                    $table->string('numero_contato', 20)->nullable()->after('credito_simples_nacional');
                } elseif (Schema::hasColumn('companies', 'cnpj')) {
                    $table->string('numero_contato', 20)->nullable()->after('cnpj');
                } else {
                    $table->string('numero_contato', 20)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('numero_contato');
        });
    }
};

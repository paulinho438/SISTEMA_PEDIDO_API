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
        Schema::table('companies', function (Blueprint $table) {
            // Identificação
            $table->string('fantasia')->nullable()->after('company');
            $table->string('razao_social')->nullable()->after('fantasia');
            
            // Endereço
            $table->string('endereco')->nullable()->after('razao_social');
            $table->string('endereco_numero', 20)->nullable()->after('endereco');
            $table->string('bairro')->nullable()->after('endereco_numero');
            $table->string('cidade')->nullable()->after('bairro');
            $table->string('uf', 2)->nullable()->after('cidade');
            $table->string('cep', 20)->nullable()->after('uf');
            
            // Documentos e Tributação
            $table->string('cnpj', 20)->nullable()->after('cep');
            $table->string('inscricao_estadual')->nullable()->after('cnpj');
            $table->string('inscricao_estadual_subst_tributario')->nullable()->after('inscricao_estadual');
            $table->string('inscricao_municipal')->nullable()->after('inscricao_estadual_subst_tributario');
            $table->string('regime_tributario')->nullable()->after('inscricao_municipal');
            $table->decimal('credito_simples_nacional', 5, 2)->nullable()->after('regime_tributario');
            
            // Renomear campos existentes se necessário
            // numero_contato já existe (Fones)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'fantasia',
                'razao_social',
                'endereco',
                'endereco_numero',
                'bairro',
                'cidade',
                'uf',
                'cep',
                'cnpj',
                'inscricao_estadual',
                'inscricao_estadual_subst_tributario',
                'inscricao_municipal',
                'regime_tributario',
                'credito_simples_nacional'
            ]);
        });
    }
};


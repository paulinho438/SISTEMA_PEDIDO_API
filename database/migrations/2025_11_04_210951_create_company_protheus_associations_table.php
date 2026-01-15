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
        Schema::create('company_protheus_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('tabela_protheus', 20)->comment('Código da tabela no Protheus (ex: SA1010, SA2010)');
            $table->string('descricao', 100)->nullable()->comment('Descrição da tabela (ex: Cliente, Fornecedor)');
            $table->timestamps();
            
            // Índice único para evitar duplicatas de tabela_protheus por empresa
            $table->unique(['company_id', 'tabela_protheus']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_protheus_associations');
    }
};

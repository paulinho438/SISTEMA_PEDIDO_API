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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo', 150);
            $table->string('cpf', 20);
            $table->string('rg', 20);
            $table->enum('sexo', ['M', 'F']);
            $table->string('telefone_celular', 20);
            $table->string('email');
            $table->string('password');
            $table->enum('status', ['A', 'I'])->default('A');
            $table->string('status_motivo')->nullable();
            $table->integer('tentativas')->default(0);
            $table->datetime('created_at')->nullable();
            $table->datetime('updated_at')->nullable();
            $table->datetime('deleted_at')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};

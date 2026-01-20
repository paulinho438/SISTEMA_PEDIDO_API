<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Para SQL Server, usar ALTER TABLE diretamente
        DB::statement('ALTER TABLE [users] ALTER COLUMN [email] NVARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Primeiro, atualizar registros com email NULL para string vazia
        DB::statement("UPDATE [users] SET [email] = '' WHERE [email] IS NULL");
        
        // Depois, alterar a coluna para NOT NULL
        DB::statement('ALTER TABLE [users] ALTER COLUMN [email] NVARCHAR(255) NOT NULL');
    }
};

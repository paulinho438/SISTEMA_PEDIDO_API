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
        // Usar SQL raw para alterar colunas no SQL Server
        // Tornar campos nullable
        // Primeiro, verificar se as colunas existem antes de alterar
        
        if (Schema::hasColumn('users', 'cpf')) {
            DB::statement('ALTER TABLE [users] ALTER COLUMN [cpf] NVARCHAR(20) NULL');
        }
        
        if (Schema::hasColumn('users', 'rg')) {
            DB::statement('ALTER TABLE [users] ALTER COLUMN [rg] NVARCHAR(20) NULL');
        }
        
        // O campo sexo é um enum que no SQL Server é armazenado como NVARCHAR(1)
        if (Schema::hasColumn('users', 'sexo')) {
            DB::statement('ALTER TABLE [users] ALTER COLUMN [sexo] NVARCHAR(1) NULL');
        }
        
        // Garantir que data_nascimento existe e é nullable
        if (!Schema::hasColumn('users', 'data_nascimento')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('data_nascimento')->nullable();
            });
        } else {
            DB::statement('ALTER TABLE [users] ALTER COLUMN [data_nascimento] DATE NULL');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverter para não nullable (com valores padrão)
        DB::statement('ALTER TABLE [users] ALTER COLUMN [cpf] NVARCHAR(20) NOT NULL');
        DB::statement('ALTER TABLE [users] ALTER COLUMN [rg] NVARCHAR(20) NOT NULL');
        DB::statement('ALTER TABLE [users] ALTER COLUMN [sexo] NVARCHAR(1) NOT NULL');
        
        // Nota: data_nascimento não será removido no down() pois pode já existir antes da migração
    }
};

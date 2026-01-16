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
        // Verificar se a coluna já existe
        if (!Schema::hasColumn('companies', 'email')) {
            Schema::table('companies', function (Blueprint $table) {
                // Adicionar email após numero_contato ou whatsapp se existir
                if (Schema::hasColumn('companies', 'whatsapp')) {
                    $table->string('email')->nullable()->after('whatsapp');
                } elseif (Schema::hasColumn('companies', 'numero_contato')) {
                    $table->string('email')->nullable()->after('numero_contato');
                } else {
                    $table->string('email')->nullable();
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
            if (Schema::hasColumn('companies', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};

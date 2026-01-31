<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Centro de custo do Protheus vem como código (ex: "6.19"), não como id numérico.
     * cost_center_id permanece para IDs numéricos (tabela local); cost_center_code armazena o código Protheus.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('cost_center_code', 50)->nullable()->after('cost_center_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('cost_center_code');
        });
    }
};

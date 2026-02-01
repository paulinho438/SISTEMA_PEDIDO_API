<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela de auditoria com índices para consultas rápidas em alto volume.
     * Permite saber quem criou/editou cada registro (user_id + action).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuário que executou a ação');
            $table->string('action', 20)->comment('created, updated, deleted');
            $table->string('auditable_type', 100)->comment('Tipo da entidade: asset, purchase_quote, asset_branch, etc.');
            $table->string('auditable_id', 36)->comment('ID do registro auditado');
            $table->json('old_values')->nullable()->comment('Valores anteriores (update/delete)');
            $table->json('new_values')->nullable()->comment('Valores novos (create/update)');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();

            // Índices para evitar lentidão em muito volume de dados
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

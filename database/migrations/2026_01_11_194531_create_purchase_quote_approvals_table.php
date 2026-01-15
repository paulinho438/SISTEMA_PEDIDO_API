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
        if (!Schema::hasTable('purchase_quote_approvals')) {
            Schema::create('purchase_quote_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->cascadeOnDelete();
                $table->enum('approval_level', [
                    'COMPRADOR',
                    'GERENTE_LOCAL',
                    'ENGENHEIRO',
                    'GERENTE_GERAL',
                    'DIRETOR',
                    'PRESIDENTE'
                ]);
                $table->boolean('required')->default(false);
                $table->boolean('approved')->default(false);
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('approved_by_name')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedInteger('order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['purchase_quote_id', 'approval_level']);
                $table->index(['purchase_quote_id', 'approved', 'order']);
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
        Schema::dropIfExists('purchase_quote_approvals');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Campos para registrar quando a solicitação foi resetada (volta para aguardando).
     */
    public function up(): void
    {
        Schema::table('purchase_quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_quotes', 'reset_reason')) {
                $table->text('reset_reason')->nullable();
            }
            if (!Schema::hasColumn('purchase_quotes', 'reset_at')) {
                $table->dateTime('reset_at')->nullable();
            }
            if (!Schema::hasColumn('purchase_quotes', 'reset_by')) {
                $table->unsignedBigInteger('reset_by')->nullable();
                $table->foreign('reset_by')->references('id')->on('users')->onDelete('no action')->onUpdate('no action');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->dropForeign(['reset_by']);
            $table->dropColumn(['reset_reason', 'reset_at', 'reset_by']);
        });
    }
};

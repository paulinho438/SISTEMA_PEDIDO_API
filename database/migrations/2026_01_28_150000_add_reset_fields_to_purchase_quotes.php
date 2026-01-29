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
            $table->text('reset_reason')->nullable()->after('nature_operation_cfop');
            $table->dateTime('reset_at')->nullable()->after('reset_reason');
            $table->foreignId('reset_by')->nullable()->after('reset_at')->constrained('users')->onDelete('set null');
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_quote_suppliers', function (Blueprint $table) {
            $table->decimal('difal_percent', 8, 4)->nullable()->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_quote_suppliers', function (Blueprint $table) {
            $table->dropColumn('difal_percent');
        });
    }
};

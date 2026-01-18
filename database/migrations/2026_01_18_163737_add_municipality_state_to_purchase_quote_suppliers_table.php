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
            $table->string('municipality', 100)->nullable()->after('supplier_document');
            $table->string('state', 2)->nullable()->after('municipality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_quote_suppliers', function (Blueprint $table) {
            $table->dropColumn(['municipality', 'state']);
        });
    }
};

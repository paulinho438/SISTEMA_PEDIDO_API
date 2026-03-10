<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responsibility_term_items', function (Blueprint $table) {
            $table->decimal('quantity_returned', 15, 4)->default(0)->after('quantity');
            $table->dateTime('returned_at')->nullable()->after('quantity_returned');
        });
    }

    public function down(): void
    {
        Schema::table('responsibility_term_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_returned', 'returned_at']);
        });
    }
};

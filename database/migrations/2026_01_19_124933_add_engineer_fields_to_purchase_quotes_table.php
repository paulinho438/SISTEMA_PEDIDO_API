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
        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->foreignId('engineer_id')->nullable()->after('buyer_name')->constrained('users')->nullOnDelete();
            $table->string('engineer_name')->nullable()->after('engineer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->dropForeign(['engineer_id']);
            $table->dropColumn(['engineer_id', 'engineer_name']);
        });
    }
};

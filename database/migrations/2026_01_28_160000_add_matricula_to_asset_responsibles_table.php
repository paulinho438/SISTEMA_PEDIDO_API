<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_responsibles', function (Blueprint $table) {
            $table->string('matricula', 50)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('asset_responsibles', function (Blueprint $table) {
            $table->dropColumn('matricula');
        });
    }
};

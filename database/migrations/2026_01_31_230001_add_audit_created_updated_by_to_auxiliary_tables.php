<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona created_by e updated_by para rastrear quem criou/editou em cadastros auxiliares.
     */
    public function up(): void
    {
        $tables = ['asset_branches', 'asset_locations', 'asset_standard_descriptions'];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('company_id');
                    $table->index('created_by');
                }
                if (!Schema::hasColumn($tableName, 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                    $table->index('updated_by');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['asset_branches', 'asset_locations', 'asset_standard_descriptions'];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'created_by')) {
                    $table->dropIndex(['created_by']);
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn($tableName, 'updated_by')) {
                    $table->dropIndex(['updated_by']);
                    $table->dropColumn('updated_by');
                }
            });
        }
    }
};

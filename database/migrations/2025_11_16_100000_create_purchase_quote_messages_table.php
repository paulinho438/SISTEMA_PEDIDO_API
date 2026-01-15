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
        Schema::create('purchase_quote_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->default('general');
            $table->text('message');
            $table->timestamps();
        });

        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->boolean('requires_response')->default(false)->after('observation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->dropColumn('requires_response');
        });

        Schema::dropIfExists('purchase_quote_messages');
    }
};


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
        Schema::create('purchase_quote_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->cascadeOnDelete();
            $table->string('supplier_code', 60)->nullable();
            $table->string('supplier_name');
            $table->string('supplier_document', 30)->nullable();
            $table->string('vendor_name')->nullable();
            $table->string('vendor_phone', 50)->nullable();
            $table->string('vendor_email')->nullable();
            $table->string('proposal_number', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_quote_supplier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_supplier_id')->constrained('purchase_quote_suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_quote_item_id')->constrained('purchase_quote_items');
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('ipi', 8, 4)->nullable();
            $table->decimal('unit_cost_with_ipi', 15, 4)->nullable();
            $table->decimal('icms', 8, 4)->nullable();
            $table->decimal('icms_total', 15, 4)->nullable();
            $table->decimal('final_cost', 15, 4)->nullable();
            $table->timestamps();
        });

        Schema::table('purchase_quote_items', function (Blueprint $table) {
            $table->foreignId('selected_supplier_id')->nullable()->after('cost_center_description')->constrained('purchase_quote_suppliers');
            $table->decimal('selected_unit_cost', 15, 4)->nullable()->after('selected_supplier_id');
            $table->decimal('selected_total_cost', 15, 4)->nullable()->after('selected_unit_cost');
            $table->text('selection_reason')->nullable()->after('selected_total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_quote_items', function (Blueprint $table) {
            $table->dropForeign(['selected_supplier_id']);
            $table->dropColumn(['selected_supplier_id', 'selected_unit_cost', 'selected_total_cost', 'selection_reason']);
        });

        Schema::dropIfExists('purchase_quote_supplier_items');
        Schema::dropIfExists('purchase_quote_suppliers');
    }
};


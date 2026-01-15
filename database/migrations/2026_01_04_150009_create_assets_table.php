<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            
            // Informações Gerais
            $table->string('asset_number', 50);
            $table->integer('increment')->default(0);
            $table->date('acquisition_date');
            $table->string('status', 50)->default('incluido');
            $table->string('movement_number', 50)->nullable();
            $table->foreignId('standard_description_id')->nullable()->constrained('asset_standard_descriptions')->onDelete('set null');
            $table->foreignId('sub_type_1_id')->nullable()->constrained('asset_sub_types_1')->onDelete('set null');
            $table->foreignId('sub_type_2_id')->nullable()->constrained('asset_sub_types_2')->onDelete('set null');
            $table->text('description')->nullable();
            
            // Características
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('tag', 100)->nullable();
            $table->string('dimension', 100)->nullable();
            $table->string('capacity', 100)->nullable();
            $table->foreignId('use_condition_id')->nullable()->constrained('asset_use_conditions')->onDelete('set null');
            $table->string('motor', 100)->nullable();
            $table->string('rpm', 50)->nullable();
            $table->integer('manufacture_year')->nullable();
            $table->string('old_number', 50)->nullable();
            $table->decimal('item_quantity', 10, 2)->default(1.00);
            $table->string('auxiliary', 100)->nullable();
            $table->text('complement')->nullable();
            
            // Fornecedor
            $table->unsignedBigInteger('supplier_id')->nullable();
            // Foreign key será adicionada manualmente se a tabela fornecedores existir
            $table->string('document_number', 100)->nullable();
            $table->date('document_issue_date')->nullable();
            $table->string('nfe_key', 100)->nullable();
            $table->text('observation')->nullable();
            $table->string('asset_url', 500)->nullable();
            
            // Classificação
            $table->foreignId('branch_id')->nullable()->constrained('asset_branches')->onDelete('set null');
            $table->foreignId('account_id')->nullable()->constrained('asset_accounts')->onDelete('set null');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            // Foreign key será adicionada manualmente se a tabela costcenter existir
            $table->foreignId('location_id')->nullable()->constrained('stock_locations')->onDelete('set null');
            $table->unsignedBigInteger('responsible_id')->nullable();
            // Foreign key será criada manualmente para evitar ciclos de cascade
            $table->foreignId('project_id')->nullable()->constrained('asset_projects')->onDelete('set null');
            $table->foreignId('business_unit_id')->nullable()->constrained('asset_business_units')->onDelete('set null');
            $table->foreignId('grouping_id')->nullable()->constrained('asset_groupings')->onDelete('set null');
            
            // Valores
            $table->decimal('value_brl', 15, 2)->default(0.00);
            $table->decimal('value_usd', 15, 2)->nullable();
            
            // Referência Compra
            $table->string('purchase_reference_type', 50)->nullable();
            $table->unsignedBigInteger('purchase_reference_id')->nullable();
            $table->string('purchase_reference_number', 100)->nullable();
            $table->unsignedBigInteger('purchase_quote_item_id')->nullable();
            
            // Auditoria
            $table->foreignId('company_id')->constrained('companies')->onDelete('no action');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            // Foreign keys serão criadas manualmente para evitar ciclos de cascade
            $table->timestamps();
            
            $table->unique(['asset_number', 'branch_id', 'company_id'], 'unique_asset_number_branch_company');
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('status');
            $table->index('tag');
            $table->index('serial_number');
            $table->index('responsible_id');
            $table->index('location_id');
            $table->index('cost_center_id');
            $table->index(['purchase_reference_type', 'purchase_reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};


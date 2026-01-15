<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_quote_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 100);
            $table->string('description')->nullable();
            $table->string('required_profile')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        DB::table('purchase_quote_statuses')->insert([
            ['slug' => 'aguardando', 'label' => 'Aguardando', 'description' => 'Solicitação de pedido de compra aguardando análise.', 'required_profile' => 'Colaborador', 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'autorizado', 'label' => 'Autorizado', 'description' => 'Diretoria/Gerência liberou para cotação.', 'required_profile' => 'Diretoria/Gerencia', 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'cotacao', 'label' => 'Cotação', 'description' => 'Supervisão encaminhou ao comprador.', 'required_profile' => 'Sup. Compras', 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'compra_em_andamento', 'label' => 'Compra em Andamento', 'description' => 'Comprador elaborando cotação.', 'required_profile' => 'Comprador', 'order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'finalizada', 'label' => 'Finalizada', 'description' => 'Cotação finalizada pelo comprador.', 'required_profile' => 'Comprador', 'order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'analisada', 'label' => 'Analisada', 'description' => 'Supervisão de compras avaliando cotação.', 'required_profile' => 'Sup. Compras', 'order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'analisada_aguardando', 'label' => 'Analisada / Aguardando', 'description' => 'Supervisão aguarda o momento da compra.', 'required_profile' => 'Sup. Compras', 'order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'analise_gerencia', 'label' => 'Análise Gerência', 'description' => 'Gerência avaliando a cotação.', 'required_profile' => 'Gerencia', 'order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'aprovado', 'label' => 'Aprovado', 'description' => 'Diretoria aprovou a cotação para virar pedido.', 'required_profile' => 'Diretoria', 'order' => 9, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('purchase_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 30)->unique();
            $table->date('requested_at')->nullable();

            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable();

            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('company_name')->nullable();

            $table->string('location')->nullable();
            $table->string('work_front')->nullable();
            $table->text('observation')->nullable();

            $table->foreignId('current_status_id')->nullable()->constrained('purchase_quote_statuses');
            $table->string('current_status_slug', 50)->nullable();
            $table->string('current_status_label', 100)->nullable();

            $table->string('main_cost_center_code', 50)->nullable();
            $table->string('main_cost_center_description')->nullable();

            $table->foreignId('buyer_id')->nullable()->constrained('users');
            $table->string('buyer_name')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
        });

        Schema::create('purchase_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->cascadeOnDelete();
            $table->string('product_code', 100)->nullable();
            $table->string('reference')->nullable();
            $table->string('description');
            $table->decimal('quantity', 15, 3)->default(0);
            $table->string('unit', 20)->nullable();
            $table->string('application')->nullable();
            $table->unsignedInteger('priority_days')->nullable();
            $table->string('tag', 100)->nullable();
            $table->string('cost_center_code', 50)->nullable();
            $table->string('cost_center_description')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_quote_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('purchase_quote_statuses');
            $table->string('status_slug', 50);
            $table->string('status_label', 100);
            $table->foreignId('acted_by')->nullable()->constrained('users');
            $table->string('acted_by_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quote_status_histories');
        Schema::dropIfExists('purchase_quote_items');
        Schema::dropIfExists('purchase_quotes');
        Schema::dropIfExists('purchase_quote_statuses');
    }
};


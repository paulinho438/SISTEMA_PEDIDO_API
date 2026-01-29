<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds protheus export fields to purchase_orders and creates views for ADVPL to read pedido + itens.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('protheus_order_number', 60)->nullable()->after('observation');
            $table->dateTime('protheus_exported_at')->nullable()->after('protheus_order_number');
        });

        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_order_export_items');
        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_order_export');

        // View: cabeçalho do pedido para exportação Protheus (uma linha por purchase_order)
        DB::unprepared(<<<'SQL'
CREATE VIEW vw_protheus_purchase_order_export AS
SELECT
    po.id AS purchase_order_id,
    po.order_number,
    po.order_date,
    po.expected_delivery_date,
    po.supplier_code,
    po.supplier_name,
    po.supplier_document,
    po.vendor_name,
    po.vendor_phone,
    po.vendor_email,
    po.proposal_number,
    po.total_amount,
    po.observation,
    po.company_id,
    po.protheus_order_number,
    po.protheus_exported_at,
    ISNULL(pqs.payment_condition_code, '') AS payment_condition_code,
    pqs.payment_condition_description,
    ISNULL(pqs.freight_type, '') AS freight_type,
    pq.nature_operation_code,
    pq.nature_operation_cfop,
    pq.main_cost_center_code,
    pq.main_cost_center_description
FROM purchase_orders AS po
INNER JOIN purchase_quote_suppliers AS pqs
    ON pqs.id = po.purchase_quote_supplier_id
INNER JOIN purchase_quotes AS pq
    ON pq.id = po.purchase_quote_id
SQL);

        // View: itens do pedido para exportação Protheus (várias linhas por purchase_order)
        DB::unprepared(<<<'SQL'
CREATE VIEW vw_protheus_purchase_order_export_items AS
SELECT
    poi.id AS purchase_order_item_id,
    poi.purchase_order_id,
    poi.purchase_quote_item_id,
    poi.product_code,
    poi.product_description,
    poi.quantity,
    ISNULL(poi.unit, 'UN') AS unit,
    poi.unit_price,
    poi.total_price,
    poi.ipi,
    poi.icms,
    poi.final_cost,
    poi.observation AS item_observation,
    ISNULL(pqi.cost_center_code, pq.main_cost_center_code) AS cost_center_code,
    ISNULL(pqi.cost_center_description, pq.main_cost_center_description) AS cost_center_description,
    ISNULL(pqi.tes_code, '') AS tes_code,
    pqi.tes_description,
    ISNULL(pqi.cfop_code, pq.nature_operation_cfop) AS cfop_code
FROM purchase_order_items AS poi
INNER JOIN purchase_quote_items AS pqi
    ON pqi.id = poi.purchase_quote_item_id
INNER JOIN purchase_quotes AS pq
    ON pq.id = poi.purchase_quote_id
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_order_export_items');
        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_order_export');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['protheus_order_number', 'protheus_exported_at']);
        });
    }
};

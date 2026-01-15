<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->string('payment_condition_code', 20)->nullable()->after('protheus_exported_at');
            $table->string('payment_condition_description')->nullable()->after('payment_condition_code');
            $table->string('freight_type', 10)->nullable()->after('payment_condition_description');
            $table->string('nature_operation_code', 20)->nullable()->after('freight_type');
            $table->string('nature_operation_description')->nullable()->after('nature_operation_code');
            $table->string('nature_operation_cfop', 10)->nullable()->after('nature_operation_description');
        });

        Schema::table('purchase_quote_items', function (Blueprint $table) {
            $table->string('tes_code', 20)->nullable()->after('selection_reason');
            $table->string('tes_description')->nullable()->after('tes_code');
            $table->string('cfop_code', 10)->nullable()->after('tes_description');
        });

        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_order_items;');
        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_orders;');

        DB::unprepared(<<<'SQL'
CREATE VIEW vw_protheus_purchase_orders AS
SELECT
    pqs.id AS integration_id,
    pq.id AS purchase_quote_id,
    pq.quote_number,
    pq.requested_at,
    pq.company_id,
    pq.company_name,
    pq.location,
    pq.work_front,
    pq.observation,
    pq.main_cost_center_code,
    pq.main_cost_center_description,
    pq.buyer_id,
    pq.buyer_name,
    pq.payment_condition_code,
    pq.payment_condition_description,
    pq.freight_type,
    pq.nature_operation_code,
    pq.nature_operation_description,
    pq.nature_operation_cfop,
    pqs.supplier_code,
    pqs.supplier_name,
    pqs.supplier_document,
    pqs.vendor_name,
    pqs.vendor_phone,
    pqs.vendor_email,
    pqs.proposal_number,
    pqs.protheus_export_status,
    pqs.protheus_order_number,
    pqs.protheus_exported_at,
    approved.approved_at,
    COUNT(DISTINCT pqi.id) AS total_items,
    SUM(
        ISNULL(
            pqi.selected_total_cost,
            ISNULL(
                psi.final_cost,
                ISNULL(psi.unit_cost, 0) * ISNULL(pqi.quantity, 0)
            )
        )
    ) AS total_value
FROM purchase_quote_suppliers AS pqs
INNER JOIN purchase_quotes AS pq
    ON pq.id = pqs.purchase_quote_id
INNER JOIN purchase_quote_items AS pqi
    ON pqi.purchase_quote_id = pq.id
    AND pqi.selected_supplier_id = pqs.id
LEFT JOIN purchase_quote_supplier_items AS psi
    ON psi.purchase_quote_supplier_id = pqs.id
    AND psi.purchase_quote_item_id = pqi.id
OUTER APPLY (
    SELECT TOP 1
        pqsh.acted_at AS approved_at
    FROM purchase_quote_status_histories AS pqsh
    WHERE pqsh.purchase_quote_id = pq.id
        AND pqsh.status_slug = 'aprovado'
    ORDER BY pqsh.acted_at DESC
) AS approved
WHERE pq.current_status_slug = 'aprovado'
  AND pqs.protheus_export_status IN ('pending', 'error')
GROUP BY
    pqs.id,
    pq.id,
    pq.quote_number,
    pq.requested_at,
    pq.company_id,
    pq.company_name,
    pq.location,
    pq.work_front,
    pq.observation,
    pq.main_cost_center_code,
    pq.main_cost_center_description,
    pq.buyer_id,
    pq.buyer_name,
    pq.payment_condition_code,
    pq.payment_condition_description,
    pq.freight_type,
    pq.nature_operation_code,
    pq.nature_operation_description,
    pq.nature_operation_cfop,
    pqs.supplier_code,
    pqs.supplier_name,
    pqs.supplier_document,
    pqs.vendor_name,
    pqs.vendor_phone,
    pqs.vendor_email,
    pqs.proposal_number,
    pqs.protheus_export_status,
    pqs.protheus_order_number,
    pqs.protheus_exported_at,
    approved.approved_at;
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW vw_protheus_purchase_order_items AS
SELECT
    pqs.id AS integration_id,
    pqi.id AS purchase_quote_item_id,
    pq.id AS purchase_quote_id,
    pq.quote_number,
    pq.company_id,
    pq.company_name,
    pq.payment_condition_code,
    pq.payment_condition_description,
    pq.freight_type,
    pq.nature_operation_code,
    pq.nature_operation_description,
    pq.nature_operation_cfop,
    pqs.supplier_code,
    pqs.supplier_name,
    pqs.supplier_document,
    pqi.product_code,
    pqi.reference,
    pqi.description,
    pqi.quantity,
    pqi.unit,
    pqi.application,
    pqi.priority_days,
    ISNULL(pqi.cost_center_code, pq.main_cost_center_code) AS cost_center_code,
    ISNULL(pqi.cost_center_description, pq.main_cost_center_description) AS cost_center_description,
    ISNULL(pqi.selected_unit_cost, ISNULL(psi.unit_cost, 0)) AS unit_cost,
    ISNULL(
        pqi.selected_total_cost,
        ISNULL(
            psi.final_cost,
            ISNULL(pqi.selected_unit_cost, ISNULL(psi.unit_cost, 0)) * ISNULL(pqi.quantity, 0)
        )
    ) AS total_cost,
    psi.ipi,
    psi.unit_cost_with_ipi,
    psi.icms,
    psi.icms_total,
    psi.final_cost,
    pqi.tes_code,
    pqi.tes_description,
    pqi.cfop_code
FROM purchase_quote_suppliers AS pqs
INNER JOIN purchase_quotes AS pq
    ON pq.id = pqs.purchase_quote_id
INNER JOIN purchase_quote_items AS pqi
    ON pqi.purchase_quote_id = pq.id
    AND pqi.selected_supplier_id = pqs.id
LEFT JOIN purchase_quote_supplier_items AS psi
    ON psi.purchase_quote_supplier_id = pqs.id
    AND psi.purchase_quote_item_id = pqi.id
WHERE pq.current_status_slug = 'aprovado'
  AND pqs.protheus_export_status IN ('pending', 'error');
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_order_items;');
        DB::statement('DROP VIEW IF EXISTS vw_protheus_purchase_orders;');

        Schema::table('purchase_quote_items', function (Blueprint $table) {
            $table->dropColumn(['tes_code', 'tes_description', 'cfop_code']);
        });

        Schema::table('purchase_quotes', function (Blueprint $table) {
            $table->dropColumn([
                'payment_condition_code',
                'payment_condition_description',
                'freight_type',
                'nature_operation_code',
                'nature_operation_description',
                'nature_operation_cfop',
            ]);
        });

        DB::unprepared(<<<'SQL'
CREATE VIEW vw_protheus_purchase_orders AS
SELECT
    pqs.id AS integration_id,
    pq.id AS purchase_quote_id,
    pq.quote_number,
    pq.requested_at,
    pq.company_id,
    pq.company_name,
    pq.location,
    pq.work_front,
    pq.observation,
    pq.main_cost_center_code,
    pq.main_cost_center_description,
    pq.buyer_id,
    pq.buyer_name,
    pqs.supplier_code,
    pqs.supplier_name,
    pqs.supplier_document,
    pqs.vendor_name,
    pqs.vendor_phone,
    pqs.vendor_email,
    pqs.proposal_number,
    pqs.protheus_export_status,
    pqs.protheus_order_number,
    pqs.protheus_exported_at,
    approved.approved_at,
    COUNT(DISTINCT pqi.id) AS total_items,
    SUM(
        ISNULL(
            pqi.selected_total_cost,
            ISNULL(
                psi.final_cost,
                ISNULL(psi.unit_cost, 0) * ISNULL(pqi.quantity, 0)
            )
        )
    ) AS total_value
FROM purchase_quote_suppliers AS pqs
INNER JOIN purchase_quotes AS pq
    ON pq.id = pqs.purchase_quote_id
INNER JOIN purchase_quote_items AS pqi
    ON pqi.purchase_quote_id = pq.id
    AND pqi.selected_supplier_id = pqs.id
LEFT JOIN purchase_quote_supplier_items AS psi
    ON psi.purchase_quote_supplier_id = pqs.id
    AND psi.purchase_quote_item_id = pqi.id
OUTER APPLY (
    SELECT TOP 1
        pqsh.acted_at AS approved_at
    FROM purchase_quote_status_histories AS pqsh
    WHERE pqsh.purchase_quote_id = pq.id
        AND pqsh.status_slug = 'aprovado'
    ORDER BY pqsh.acted_at DESC
) AS approved
WHERE pq.current_status_slug = 'aprovado'
  AND pqs.protheus_export_status IN ('pending', 'error')
GROUP BY
    pqs.id,
    pq.id,
    pq.quote_number,
    pq.requested_at,
    pq.company_id,
    pq.company_name,
    pq.location,
    pq.work_front,
    pq.observation,
    pq.main_cost_center_code,
    pq.main_cost_center_description,
    pq.buyer_id,
    pq.buyer_name,
    pqs.supplier_code,
    pqs.supplier_name,
    pqs.supplier_document,
    pqs.vendor_name,
    pqs.vendor_phone,
    pqs.vendor_email,
    pqs.proposal_number,
    pqs.protheus_export_status,
    pqs.protheus_order_number,
    pqs.protheus_exported_at,
    approved.approved_at;
SQL);

        DB::unprepared(<<<'SQL'
CREATE VIEW vw_protheus_purchase_order_items AS
SELECT
    pqs.id AS integration_id,
    pqi.id AS purchase_quote_item_id,
    pq.id AS purchase_quote_id,
    pq.quote_number,
    pq.company_id,
    pq.company_name,
    pqs.supplier_code,
    pqs.supplier_name,
    pqs.supplier_document,
    pqi.product_code,
    pqi.reference,
    pqi.description,
    pqi.quantity,
    pqi.unit,
    pqi.application,
    pqi.priority_days,
    ISNULL(pqi.cost_center_code, pq.main_cost_center_code) AS cost_center_code,
    ISNULL(pqi.cost_center_description, pq.main_cost_center_description) AS cost_center_description,
    ISNULL(pqi.selected_unit_cost, ISNULL(psi.unit_cost, 0)) AS unit_cost,
    ISNULL(
        pqi.selected_total_cost,
        ISNULL(
            psi.final_cost,
            ISNULL(pqi.selected_unit_cost, ISNULL(psi.unit_cost, 0)) * ISNULL(pqi.quantity, 0)
        )
    ) AS total_cost,
    psi.ipi,
    psi.unit_cost_with_ipi,
    psi.icms,
    psi.icms_total,
    psi.final_cost
FROM purchase_quote_suppliers AS pqs
INNER JOIN purchase_quotes AS pq
    ON pq.id = pqs.purchase_quote_id
INNER JOIN purchase_quote_items AS pqi
    ON pqi.purchase_quote_id = pq.id
    AND pqi.selected_supplier_id = pqs.id
LEFT JOIN purchase_quote_supplier_items AS psi
    ON psi.purchase_quote_supplier_id = pqs.id
    AND psi.purchase_quote_item_id = pqi.id
WHERE pq.current_status_slug = 'aprovado'
  AND pqs.protheus_export_status IN ('pending', 'error');
SQL);
    }
};


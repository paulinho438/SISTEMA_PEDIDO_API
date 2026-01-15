<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\PurchaseQuoteSupplier;
use App\Services\PurchaseQuote\PurchaseQuoteProtheusExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtheusIntegrationController extends Controller
{
    public function processing(
        Request $request,
        PurchaseQuoteSupplier $supplier,
        PurchaseQuoteProtheusExportService $service
    ) {
        $service->markSupplierInProcess($supplier);

        $supplier->loadMissing('quote:id,quote_number,protheus_export_status');

        return response()->json([
            'message' => 'Registro marcado como em processamento.',
            'supplier' => [
                'id' => $supplier->id,
                'status' => $supplier->protheus_export_status,
                'attempts' => $supplier->protheus_export_attempts,
            ],
            'quote' => [
                'id' => $supplier->quote->id ?? null,
                'quote_number' => $supplier->quote->quote_number ?? null,
                'protheus_export_status' => $supplier->quote->protheus_export_status ?? null,
            ],
        ], Response::HTTP_OK);
    }

    public function complete(
        Request $request,
        PurchaseQuoteSupplier $supplier,
        PurchaseQuoteProtheusExportService $service
    ) {
        $validated = $request->validate([
            'order_number' => 'required|string|max:60',
            'message' => 'nullable|string',
        ]);

        $service->markSupplierSuccess($supplier, $validated['order_number'], $validated['message'] ?? null);

        $supplier->loadMissing('quote:id,quote_number,protheus_export_status,protheus_exported_at');

        return response()->json([
            'message' => 'Exportação registrada com sucesso.',
            'supplier' => [
                'id' => $supplier->id,
                'status' => $supplier->protheus_export_status,
                'order_number' => $supplier->protheus_order_number,
                'exported_at' => optional($supplier->protheus_exported_at)->toIso8601String(),
            ],
            'quote' => [
                'id' => $supplier->quote->id ?? null,
                'quote_number' => $supplier->quote->quote_number ?? null,
                'protheus_export_status' => $supplier->quote->protheus_export_status ?? null,
                'protheus_exported_at' => optional($supplier->quote->protheus_exported_at ?? null)->toIso8601String(),
            ],
        ], Response::HTTP_OK);
    }

    public function fail(
        Request $request,
        PurchaseQuoteSupplier $supplier,
        PurchaseQuoteProtheusExportService $service
    ) {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $service->markSupplierFailure($supplier, $validated['message']);

        $supplier->loadMissing('quote:id,quote_number,protheus_export_status');

        return response()->json([
            'message' => 'Falha registrada para o fornecedor informado.',
            'supplier' => [
                'id' => $supplier->id,
                'status' => $supplier->protheus_export_status,
                'last_error' => $supplier->protheus_last_error,
            ],
            'quote' => [
                'id' => $supplier->quote->id ?? null,
                'quote_number' => $supplier->quote->quote_number ?? null,
                'protheus_export_status' => $supplier->quote->protheus_export_status ?? null,
            ],
        ], Response::HTTP_OK);
    }
}


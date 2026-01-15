<?php

namespace App\Http\Controllers;

use App\Services\PurchaseInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PurchaseInvoiceController extends Controller
{
    protected $service;

    public function __construct(PurchaseInvoiceService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $companyId = $request->header('company-id');
        
        $filters = [
            'company_id' => $companyId,
            'purchase_quote_id' => $request->get('purchase_quote_id'),
            'invoice_number' => $request->get('invoice_number'),
            'supplier_name' => $request->get('supplier_name'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $perPage = (int) $request->get('per_page', 15);
        $invoices = $this->service->list($filters, $perPage);

        return response()->json([
            'data' => $invoices
        ]);
    }

    public function show(Request $request, $id)
    {
        $invoice = $this->service->find($id);
        
        return response()->json([
            'data' => $invoice
        ]);
    }

    public function buscarPedido(Request $request, $orderId)
    {
        try {
            $order = $this->service->buscarPedidoParaNota($orderId);
            
            return response()->json([
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao buscar pedido.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:50',
            'invoice_series' => 'nullable|string|max:10',
            'invoice_date' => 'required|date',
            'received_date' => 'nullable|date',
            'purchase_quote_id' => 'nullable|exists:purchase_quotes,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'supplier_name' => 'nullable|string|max:255',
            'supplier_document' => 'nullable|string|max:20',
            'total_amount' => 'nullable|numeric|min:0',
            'observation' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_code' => 'nullable|string|max:100',
            'items.*.product_description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.purchase_quote_item_id' => 'nullable|exists:purchase_quote_items,id',
            'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
            'items.*.stock_location_id' => 'required|exists:stock_locations,id',
            'items.*.observation' => 'nullable|string',
        ]);

        $companyId = $request->header('company-id');

        if (!$companyId) {
            return response()->json([
                'message' => 'Company ID é obrigatório.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Converter para inteiro
        $companyId = (int) $companyId;

        DB::beginTransaction();

        try {
            $invoice = $this->service->criarNotaFiscalEDarEntrada($validated, $companyId, auth()->id());
            
            DB::commit();

            return response()->json([
                'message' => 'Nota fiscal criada e entrada no estoque realizada com sucesso.',
                'data' => $invoice
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Erro ao criar nota fiscal.',
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}

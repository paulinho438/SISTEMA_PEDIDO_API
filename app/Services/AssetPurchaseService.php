<?php

namespace App\Services;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteItem;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Services\AssetService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetPurchaseService
{
    protected $assetService;

    public function __construct(AssetService $assetService)
    {
        $this->assetService = $assetService;
    }

    /**
     * Criar ativos a partir de cotação aprovada
     */
    public function criarAtivosPorCompra(PurchaseQuote $quote): void
    {
        DB::beginTransaction();

        try {
            $companyId = $quote->company_id;

            // Buscar itens da cotação que foram selecionados
            $items = PurchaseQuoteItem::where('purchase_quote_id', $quote->id)
                ->whereHas('suppliers', function($query) {
                    $query->where('selected', true);
                })
                ->get();

            foreach ($items as $item) {
                // Buscar fornecedor selecionado para este item
                $selectedSupplier = $item->suppliers()
                    ->where('selected', true)
                    ->first();

                if (!$selectedSupplier) {
                    continue;
                }

                // Verificar se item deve virar ativo (pode ser por tipo, valor, etc)
                // Por enquanto, criar ativo para todos os itens selecionados
                // TODO: Implementar lógica de verificação se deve virar ativo

                $quantity = (int) ($selectedSupplier->quantity ?? 1);
                $unitPrice = (float) ($selectedSupplier->unit_price ?? 0);

                // Criar um ativo por unidade
                for ($i = 0; $i < $quantity; $i++) {
                    $assetData = [
                        'acquisition_date' => $quote->created_at->toDateString(),
                        'description' => $item->description,
                        'value_brl' => $unitPrice,
                        'supplier_id' => $selectedSupplier->supplier_id ?? null,
                        'purchase_reference_type' => 'compra',
                        'purchase_reference_id' => $quote->id,
                        'purchase_reference_number' => $quote->quote_number ?? (string) $quote->id,
                        'purchase_quote_item_id' => $item->id,
                        'document_number' => null, // Pode vir da cotação
                        'observation' => "Criado a partir da cotação #{$quote->id}",
                    ];

                    $asset = $this->assetService->create($assetData, $companyId, auth()->id());

                    // Criar movimentação de cadastro via compra
                    AssetMovement::where('asset_id', $asset->id)
                        ->where('movement_type', 'cadastro')
                        ->update([
                            'reference_type' => 'compra',
                            'reference_id' => $quote->id,
                            'reference_number' => $quote->quote_number ?? (string) $quote->id,
                        ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}


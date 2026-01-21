<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'purchase_invoice_id' => $this->purchase_invoice_id,
            'purchase_quote_id' => $this->purchase_quote_id,
            'purchase_quote_item_id' => $this->purchase_quote_item_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'stock_product_id' => $this->stock_product_id,
            'stock_location_id' => $this->stock_location_id,
            'product_code' => $this->product_code,
            'product_description' => $this->product_description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'observation' => $this->observation,
            'product' => $this->whenLoaded('product', function() {
                return [
                    'id' => $this->product->id,
                    'code' => $this->product->code,
                    'description' => $this->product->description,
                ];
            }),
            'location' => $this->whenLoaded('stockLocation', function() {
                return [
                    'id' => $this->stockLocation->id,
                    'name' => $this->stockLocation->name,
                    'code' => $this->stockLocation->code,
                ];
            }),
        ];
    }
}

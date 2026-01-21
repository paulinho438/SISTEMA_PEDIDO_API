<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_series' => $this->invoice_series,
            'invoice_date' => $this->invoice_date ? $this->invoice_date->format('d/m/Y') : null,
            'received_date' => $this->received_date ? $this->received_date->format('d/m/Y') : null,
            'purchase_quote_id' => $this->purchase_quote_id,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_name' => $this->supplier_name,
            'supplier_document' => $this->supplier_document,
            'total_amount' => (float) $this->total_amount,
            'observation' => $this->observation,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? \Carbon\Carbon::parse($this->created_at)->format('d/m/Y H:i:s') : null,
            'updated_at' => $this->updated_at ? \Carbon\Carbon::parse($this->updated_at)->format('d/m/Y H:i:s') : null,
            'created_by_user' => $this->whenLoaded('createdBy', function() {
                return [
                    'id' => $this->createdBy->id,
                    'nome_completo' => $this->createdBy->nome_completo ?? $this->createdBy->name ?? '-',
                ];
            }),
            'items' => PurchaseInvoiceItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', function() {
                return $this->items->count();
            }),
            'quote' => $this->whenLoaded('quote', function() {
                return [
                    'id' => $this->quote->id,
                    'number' => $this->quote->number ?? '-',
                ];
            }),
            'order' => $this->whenLoaded('order', function() {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number ?? '-',
                ];
            }),
        ];
    }
}

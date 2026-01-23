<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'stock_id' => $this->stock_id,
            'product' => [
                'id' => $this->product->id ?? null,
                'code' => $this->product->code ?? null,
                'description' => $this->product->description ?? null,
            ],
            'quantity' => (float) $this->quantity,
            'quantity_available_before' => (float) $this->quantity_available_before,
            'destination_stock_available' => isset($this->destination_stock_available) ? (float) $this->destination_stock_available : null,
        ];
    }
}


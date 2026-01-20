<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class StockResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', function() {
                return new StockProductResource($this->product);
            }),
            'location' => $this->whenLoaded('location', function() {
                return new StockLocationResource($this->location);
            }),
            'quantity_available' => (float) $this->quantity_available,
            'quantity_reserved' => (float) $this->quantity_reserved,
            'quantity_total' => (float) $this->quantity_total,
            'min_stock' => $this->min_stock ? (float) $this->min_stock : null,
            'max_stock' => $this->max_stock ? (float) $this->max_stock : null,
            'last_movement_at' => $this->last_movement_at ? $this->last_movement_at->format('d/m/Y H:i:s') : null,
            'company_id' => $this->company_id,
            'reservation_date' => isset($this->reservation_date) && $this->reservation_date
                ? (Carbon::parse($this->reservation_date)->format('d/m/Y'))
                : null,
            'reservation_user' => isset($this->reservation_user) && $this->reservation_user
                ? [
                    'id' => $this->reservation_user->id,
                    'name' => $this->reservation_user->nome_completo ?? $this->reservation_user->name ?? 'N/A',
                ]
                : null,
            'movements' => $this->whenLoaded('movements', function() {
                return StockMovementResource::collection($this->movements);
            }),
        ];
    }
}


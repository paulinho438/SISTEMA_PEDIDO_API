<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product' => new StockProductResource($this->whenLoaded('product')),
            'location' => new StockLocationResource($this->whenLoaded('location')),
            'movement_type' => $this->movement_type,
            'quantity' => (float) $this->quantity,
            'quantity_before' => (float) $this->quantity_before,
            'quantity_after' => (float) $this->quantity_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $this->reference_number,
            'cost' => $this->cost ? (float) $this->cost : null,
            'total_cost' => $this->total_cost ? (float) $this->total_cost : null,
            'observation' => $this->observation,
            'user' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'nome_completo' => $this->user->nome_completo,
                ];
            }),
            'movement_date' => $this->movement_date ? $this->movement_date->format('d/m/Y') : null,
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y H:i:s') : null,
        ];
    }
}


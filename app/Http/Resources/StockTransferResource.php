<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'origin_location' => [
                'id' => $this->originLocation->id ?? null,
                'name' => $this->originLocation->name ?? null,
                'code' => $this->originLocation->code ?? null,
            ],
            'destination_location' => [
                'id' => $this->destinationLocation->id ?? null,
                'name' => $this->destinationLocation->name ?? null,
                'code' => $this->destinationLocation->code ?? null,
            ],
            'driver_name' => $this->driver_name,
            'license_plate' => $this->license_plate,
            'status' => $this->status,
            'status_label' => $this->status === 'pendente' ? 'Pendente' : 'Recebido',
            'observation' => $this->observation,
            'user' => [
                'id' => $this->user->id ?? null,
                'name' => $this->user->nome_completo ?? null,
            ],
            'items' => StockTransferItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenLoaded('items', function () {
                return $this->items->count();
            }),
            'total_quantity' => $this->whenLoaded('items', function () {
                return $this->items->sum('quantity');
            }),
            'created_at' => $this->created_at ? \Carbon\Carbon::parse($this->created_at)->format('d/m/Y H:i:s') : null,
            'updated_at' => $this->updated_at ? \Carbon\Carbon::parse($this->updated_at)->format('d/m/Y H:i:s') : null,
        ];
    }
}


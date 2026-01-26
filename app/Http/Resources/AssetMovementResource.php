<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssetMovementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'movement_type' => $this->movement_type,
            'movement_date' => $this->movement_date ? $this->movement_date->format('d/m/Y') : null,
            'observation' => $this->observation,
            'user' => $this->whenLoaded('user', function() {
                return [
                    'id' => $this->user->id,
                    'nome_completo' => $this->user->nome_completo,
                ];
            }),
            'to_branch' => $this->whenLoaded('toBranch', function() {
                return ['id' => $this->toBranch->id, 'name' => $this->toBranch->name];
            }),
            'to_location' => $this->whenLoaded('toLocation', function() {
                return ['id' => $this->toLocation->id, 'name' => $this->toLocation->name];
            }),
            'to_responsible' => $this->whenLoaded('toResponsible', function() {
                return ['id' => $this->toResponsible->id, 'name' => $this->toResponsible->name];
            }),
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y H:i:s') : null,
        ];
    }
}


<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssetProjectResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date ? $this->start_date->format('d/m/Y') : null,
            'end_date' => $this->end_date ? $this->end_date->format('d/m/Y') : null,
            'active' => $this->active,
        ];
    }
}


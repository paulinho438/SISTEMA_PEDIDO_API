<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'reference' => $this->reference,
            'description' => $this->description,
            'unit' => $this->unit,
            'active' => $this->active,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d/m/Y H:i:s') : null,
            'locations' => $this->whenLoaded('locations', function() {
                return $this->locations;
            }),
        ];
    }
}


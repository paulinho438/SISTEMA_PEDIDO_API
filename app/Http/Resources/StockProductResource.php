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
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'image_path' => $this->image_path,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('d/m/Y H:i:s') : null,
            'locations' => $this->whenLoaded('locations', function() {
                return $this->locations;
            }),
            'stocks' => $this->whenLoaded('stocks', function() {
                return $this->stocks->map(function($stock) {
                    return [
                        'id' => $stock->id,
                        'location_id' => $stock->stock_location_id,
                        'location_name' => $stock->location->name ?? null,
                        'location_code' => $stock->location->code ?? null,
                        'quantity_available' => $stock->quantity_available,
                        'quantity_reserved' => $stock->quantity_reserved,
                        'quantity_total' => $stock->quantity_total,
                        'last_movement_at' => $stock->last_movement_at ? $stock->last_movement_at->format('d/m/Y H:i:s') : null,
                    ];
                });
            }),
        ];
    }
}


<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'asset_number' => $this->asset_number,
            'increment' => $this->increment,
            'acquisition_date' => $this->acquisition_date ? $this->acquisition_date->format('d/m/Y') : null,
            'status' => $this->status,
            'description' => $this->description,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'tag' => $this->tag,
            'value_brl' => (float) $this->value_brl,
            'value_usd' => $this->value_usd ? (float) $this->value_usd : null,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', function() {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                ];
            }),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', function() {
                return [
                    'id' => $this->location->id,
                    'name' => $this->location->name,
                ];
            }),
            'responsible_id' => $this->responsible_id,
            'responsible' => $this->whenLoaded('responsible', function() {
                return [
                    'id' => $this->responsible->id,
                    'nome_completo' => $this->responsible->nome_completo,
                ];
            }),
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->whenLoaded('costCenter', function() {
                return [
                    'id' => $this->costCenter->id,
                    'name' => $this->costCenter->name ?? $this->costCenter->description ?? '-',
                ];
            }),
            'account_id' => $this->account_id,
            'project_id' => $this->project_id,
            'supplier' => $this->whenLoaded('supplier', function() {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->nome_completo ?? $this->supplier->name ?? '-',
                ];
            }),
            'movements' => $this->whenLoaded('movements', function() {
                return AssetMovementResource::collection($this->movements);
            }),
            'images' => $this->whenLoaded('images', function() {
                return AssetImageResource::collection($this->images);
            }),
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? url('/storage/' . $this->image_path) : null,
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y H:i:s') : null,
        ];
    }
}


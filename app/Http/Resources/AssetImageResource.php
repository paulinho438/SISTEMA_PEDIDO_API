<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssetImageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'image_path' => $this->image_path,
            'image_name' => $this->image_name,
            'is_primary' => $this->is_primary,
            'order_index' => $this->order_index,
        ];
    }
}


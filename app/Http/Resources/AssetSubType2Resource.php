<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssetSubType2Resource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'asset_sub_type_1_id' => $this->asset_sub_type_1_id,
            'sub_type_1' => $this->whenLoaded('subType1', function() {
                return ['id' => $this->subType1->id, 'name' => $this->subType1->name];
            }),
            'active' => $this->active,
        ];
    }
}


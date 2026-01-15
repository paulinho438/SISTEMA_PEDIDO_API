<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

class CostcenterResource extends JsonResource
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
            "id"                => $this->id,
            "name"              => $this->name,
            "description"       => $this->description,
            "created_at"        => $this->created_at ? ($this->created_at instanceof \DateTime || $this->created_at instanceof \Carbon\Carbon ? $this->created_at->format('d/m/Y H:i:s') : $this->created_at) : null,
        ];
    }
}

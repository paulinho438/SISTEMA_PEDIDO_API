<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use DateTime;

class FeriadoResource extends JsonResource
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
            "id"                    => $this->id,
            "description"           => $this->description,
            "data_feriado"          => (new DateTime($this->data_feriado))->format('d/m/Y'),
        ];
    }
}


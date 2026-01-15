<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

class AddressResource extends JsonResource
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
            "id" => $this->id,
            "address" => $this->address,
            "cep" => $this->cep,
            "number" => $this->number,
            "complement" => $this->complement,
            "neighborhood" => $this->neighborhood,
            "city" => $this->city,
            "latitude" => $this->latitude,
            "longitude" => $this->longitude,
            "client_id" => $this->client_id,
            "description" => $this->description,
        ];
    }
}

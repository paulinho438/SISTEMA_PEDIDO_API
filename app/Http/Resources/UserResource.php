<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            "nome_completo"     => $this->nome_completo,
            "email"             => $this->email,
            "login"             => $this->login,
            "company"           => $this->companies,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

class EmpresaResource extends JsonResource
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
            "company"           => $this->company,
            "created_at"        => $this->created_at,
            "updated_at"        => $this->updated_at,
            "juros"             => $this->juros,
            "caixa"             => $this->caixa,
            "whatsapp"          => $this->whatsapp,
            "email"             => $this->email,
            "ativo"             => $this->ativo,
            "motivo_inativo"    => $this->motivo_inativo

        ];
    }
}

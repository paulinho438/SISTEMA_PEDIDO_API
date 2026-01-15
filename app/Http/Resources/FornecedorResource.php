<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use DateTime;

class FornecedorResource extends JsonResource
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
            "nome_completo"         => $this->nome_completo,
            "cpfcnpj"               => $this->cpfcnpj,
            "telefone_celular_1"    => $this->telefone_celular_1,
            "telefone_celular_2"    => $this->telefone_celular_2,
            "address"               => $this->address,
            "cep"                   => $this->cep,
            "number"                => $this->number,
            "complement"            => $this->complement,
            "neighborhood"          => $this->neighborhood,
            "city"                  => $this->city,
            "observation"           => $this->observation,
            "created_at"            => $this->created_at->format('d/m/Y H:i:s'),
            "pix_fornecedor"        => $this->pix_fornecedor

        ];
    }
}

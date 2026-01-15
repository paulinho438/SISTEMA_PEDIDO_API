<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use DateTime;

class MovimentacaofinanceiraResource extends JsonResource
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
            "descricao"             => $this->descricao,
            "tipomov"               => $this->tipomov,
            "dt_movimentacao"       => (new DateTime($this->dt_movimentacao))->format('d/m/Y'),
            "valor"                 => $this->valor,
            "banco"                 => $this->banco,

        ];
    }
}

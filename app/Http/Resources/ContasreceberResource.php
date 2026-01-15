<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use DateTime;

class ContasreceberResource extends JsonResource
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
            "status"                => $this->status,
            "tipodoc"               => $this->tipodoc,
            "descricao"             => $this->descricao,
            "lanc"                  => $this->lanc,
            "venc"                  => (new DateTime($this->venc))->format('d/m/Y'),
            "dt_baixa"              => $this->dt_baixa?(new DateTime($this->dt_baixa))->format('d/m/Y'):null,
            "forma_recebto"         => $this->forma_recebto,
            "valor"                 => $this->valor,
            "banco"                 => new BancosResource($this->banco),
            "parcela"               => $this->parcela,
            "cliente"               => new ClientResource($this->cliente),

        ];
    }
}

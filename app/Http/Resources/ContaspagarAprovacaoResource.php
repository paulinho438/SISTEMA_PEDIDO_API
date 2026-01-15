<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use DateTime;

class ContaspagarAprovacaoResource extends JsonResource
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
            "dt_baixa"              => $this->dt_baixa ? (new DateTime($this->dt_baixa))->format('d/m/Y') : null,
            "valor"                 => $this->valor,
            "banco"                 => new BancosComSaldoResource($this->banco),
            "emprestimo"            => $this->emprestimo,
            "cliente"               => $this->emprestimo->client ?? null,
            "fornecedor"            => new FornecedorResource($this->fornecedor),
            // "costcenter"            => $this->costcenter,
            "qt_parcelas" => isset($this->emprestimo) && isset($this->emprestimo->parcelas) ? count($this->emprestimo->parcelas) : null,

        ];
    }
}

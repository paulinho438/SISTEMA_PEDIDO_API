<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use DateTime;

class PagamentoSaldoPendenteResource extends JsonResource
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
            "emprestimo_id"         => $this->emprestimo_id,
            "valor"                 => $this->formatarMoeda($this->valor),
            "valorSemFormatacao"    => $this->valor,
            "identificador"         => $this->identificador,
            "chave_pix"             => ($this->chave_pix != null) ? $this->chave_pix : '',
        ];
    }

    /**
     * Formata um valor decimal como uma string formatada no formato de moeda brasileira (R$).
     *
     * @param float $valor O valor decimal a ser formatado.
     * @return string A string formatada no formato de moeda brasileira (R$).
     */
    function formatarMoeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}


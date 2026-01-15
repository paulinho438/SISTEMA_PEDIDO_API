<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Emprestimo;
use DateTime;

class EmprestimoLandingPageResource extends JsonResource
{
    public function porcent($vl1, $vl2)
    {
        if ($vl1 != 0) {
            return number_format(($vl2 / $vl1) * 100, 1);
        } else {
            return 0;
        }
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $parcelas = $this->parcelas;
        $saldoareceber = $parcelas->where('dt_baixa', null)->sum('saldo');

        return [
            "id" => $this->id,
            "dt_lancamento" => (new DateTime($this->dt_lancamento))->format('d/m/Y'),
            "valor" => $this->valor,
            "lucro" => $this->lucro,
            "juros" => $this->juros,
            "liberar_minimo" => $this->liberar_minimo,
            "saldoareceber" => $saldoareceber,
            "parcelas" => ParcelaResource::collection($parcelas->sortBy('parcela')),
            "quitacao" => new QuitacaoResource($this->quitacao),
            "pagamentominimo" => $this->liberar_minimo == 1 ? new PagamentoMinimoResource($this->pagamentominimo) : null,
            "pagamentosaldopendente" => new PagamentoSaldoPendenteResource($this->pagamentosaldopendente),
            "telefone_empresa" => $this->company->numero_contato,
        ];
    }
}

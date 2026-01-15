<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use Carbon\Carbon;

use DateTime;

class ParcelaResource extends JsonResource
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
            "emprestimo_id" => $this->emprestimo_id,
            "parcela" => $this->parcela,
            "valor" => $this->formatarMoeda($this->valor),
            "saldo" => $this->saldo,
            "multa" => $this->formatarMoeda(($this->saldo + $this->totalPagoParcela()) - $this->valor),
            "venc" => (new DateTime($this->venc))->format('d/m/Y'),
            "venc_real" => (new DateTime($this->venc_real))->format('d/m/Y'),
            "dt_lancamento" => (new DateTime($this->dt_lancamento))->format('d/m/Y'),
            "dt_baixa" => ($this->dt_baixa != null) ? Carbon::parse($this->dt_baixa, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y') : '',
            "dt_ult_cobranca" => $this->dt_ult_cobranca,
            "identificador" => $this->identificador,
            "chave_pix" => ($this->chave_pix != null) ? $this->chave_pix : $this->emprestimo->banco->chavepix,
            "nome_cliente" => $this->emprestimo->client->nome_completo ?? null,
            "cpf" => $this->emprestimo->client->cpf ?? null,
            "telefone_celular_1" => $this->emprestimo->client->telefone_celular_1 ?? null,
            "telefone_celular_2" => $this->emprestimo->client->telefone_celular_2 ?? null,
            "atrasadas" => $this->atrasadas,
            "latitude" => $this->getLatitudeFromAddress(),
            "longitude" => $this->getLongitudeFromAddress(),
            "endereco" => $this->getEnderecoFromAddress(),
            "total_pago_emprestimo" => $this->formatarMoeda($this->totalPagoEmprestimo()),
            "total_pago_parcela" => $this->formatarMoeda($this->totalPagoParcela()),
            "total_pendente" => $this->formatarMoeda($this->totalPendente()),
            "total_pendente_hoje" => $this->totalPendenteHoje(),
            "valor_recebido" => $this->valor_recebido,
            "valor_recebido_pix" => $this->valor_recebido_pix,
            "beneficiario" => $this->emprestimo->banco->info_recebedor_pix,
        ];
    }

    /**
     * Retorna a latitude do endereço.
     *
     * @return string|null
     */
    protected function getLatitudeFromAddress()
    {
        if (isset($this->emprestimo->client->address[0]->latitude)) {
            return $this->emprestimo->client->address[0]->latitude;
        }
        return null;
    }

    /**
     * Retorna a latitude do endereço.
     *
     * @return string|null
     */
    protected function getLongitudeFromAddress()
    {
        if (isset($this->emprestimo->client->address[0]->longitude)) {
            return $this->emprestimo->client->address[0]->longitude;
        }
        return null;
    }

    /**
     * Retorna a latitude do endereço.
     *
     * @return string|null
     */
    protected function getEnderecoFromAddress()
    {
        if (isset($this->emprestimo->client->address[0]->address)) {
            return $this->emprestimo->company->company. ' ' . $this->emprestimo->client->address[0]->neighborhood . ' ' . $this->emprestimo->client->address[0]->address . ' ' . $this->emprestimo->client->address[0]->number. ' ' . $this->emprestimo->client->address[0]->complement;
        }
        return null;
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


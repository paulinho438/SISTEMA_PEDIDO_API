<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;
use App\Models\Parcela;
use App\Services\BcodexService;
use App\Models\CustomLog;

use Efi\Exception\EfiException;
use Efi\EfiPay;

class BancosComSaldoResource extends JsonResource
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
            "name" => $this->name,
            "agencia" => $this->agencia,
            "conta" => $this->conta,
            "saldo" => $this->saldo,
            "saldo_banco" => $this->getSaldoBanco(),
            "parcelas_baixa_manual" => $this->getParcelasBaixaManual(),
            "caixa_empresa" => $this->company->caixa,
            "caixa_pix" => $this->company->caixa_pix,
            "wallet" => ($this->wallet) ? true : false,
            "juros" => $this->juros,
            "document" => $this->document,
            "accountId" => $this->accountId,
            "chavepix" => $this->chavepix,
            "info_recebedor_pix" => $this->info_recebedor_pix,
            "created_at" => $this->created_at->format('d/m/Y H:i:s'),
            "name_agencia_conta" => "{$this->name} - Agência {$this->agencia} Cc {$this->conta}",
        ];
    }

    private function getSaldoBanco()
    {

        if ($this->wallet) {

            $bcodexService = new BcodexService();

            $response = $bcodexService->consultarSaldo($this->accountId);

            if (is_object($response) && method_exists($response, 'successful') && $response->successful()) {

                return ($response->json()['balance'] / 100);
            }

        } else {
            return null;
        }

    }

    private function getParcelasBaixaManual()
    {

        $id = $this->id;

        $parcelas = Parcela::whereHas('emprestimo', function ($query) use ($id) {
            $query->where('banco_id', $id)
                  ->whereNull('dt_baixa')
                  ->where('valor_recebido', '>', 0);
        })->get();

        return $parcelas;

    }
}

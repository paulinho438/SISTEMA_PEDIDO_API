<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;

use App\Models\CustomLog;

use Efi\Exception\EfiException;
use Efi\EfiPay;

class BancosResource extends JsonResource
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
            "caixa_empresa" => $this->company->caixa,
            "caixa_pix" => $this->company->caixa_pix,
            "wallet" => ($this->wallet) ? true : false,
            "clienteid" => $this->clienteid,
            "clientesecret" => $this->clientesecret,
            "juros" => $this->juros,
            "certificado" => $this->certificado,
            "chavepix" => $this->chavepix,
            "info_recebedor_pix" => $this->info_recebedor_pix,
            "created_at" => $this->created_at->format('d/m/Y H:i:s'),
            "name_agencia_conta" => "{$this->name} - Agência {$this->agencia} Cc {$this->conta}",
        ];
    }

}

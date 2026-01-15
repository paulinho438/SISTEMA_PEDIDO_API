<?php

namespace App\Http\Resources;

use Carbon\Carbon;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;
use App\Models\Locacao;
use App\Models\Company;


class LoginClienteResource extends JsonResource
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
            "login"             => $this->login,
            "nome_completo"     => $this->nome_completo,
            "cpf"               => $this->cpf,
            "rg"                => $this->rg,
            "data_nascimento"   => $this->data_nascimento,
            "sexo"              => $this->sexo,
            "telefone_celular"  => $this->telefone_celular_1,
            "email"             => $this->email,
            "status"            => $this->status,
            "status_motivo"     => $this->status_motivo,
            "permissions"       => [],
            "companies"         => $this->getFilteredCompanies(),

        ];
    }

    public function getFilteredCompanies()
    {
        $comp = [];

        $company = $this->company;

        if ($company->locacoes->isEmpty()) {
            $comp[] = $company;
        } else {
            $ultimaLocacao = $company->locacoes->last();
            if ($ultimaLocacao->data_vencimento >= Carbon::today()->toDateString() || $ultimaLocacao->data_vencimento < Carbon::today()->toDateString() && $ultimaLocacao->data_pagamento != null) {
                $comp[] = $company;
            }
        }

        return $comp;
    }
}

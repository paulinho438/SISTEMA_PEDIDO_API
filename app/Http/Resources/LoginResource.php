<?php

namespace App\Http\Resources;

use Carbon\Carbon;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Permgroup;
use App\Models\Locacao;
use App\Models\Company;


class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "login" => $this->login,
            "nome_completo" => $this->nome_completo,
            "cpf" => $this->cpf,
            "rg" => $this->rg,
            "data_nascimento" => $this->data_nascimento,
            "sexo" => $this->sexo,
            "telefone_celular" => $this->telefone_celular,
            "email" => $this->email,
            "status" => $this->status,
            "status_motivo" => $this->status_motivo,
            "tentativas" => $this->tentativas,
            // "companies"         => CompaniesResource::collection($this->companies),
            "permissions" => PermissionsResource::collection($this->groups),
            "companies" => $this->getFilteredCompanies(),
            // "permissions"       => $this->groups

        ];
    }

    public function getFilteredCompanies()
    {
        $comp = [];

        foreach ($this->companies as $company) {
            $comp[] = $company;
        }

        return $comp;
    }
}

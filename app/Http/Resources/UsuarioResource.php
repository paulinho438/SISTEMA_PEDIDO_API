<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Models\Permgroup;

use DateTime;

class UsuarioResource extends JsonResource
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
            "login"                 => $this->login,
            "nome_completo"         => $this->nome_completo,
            "rg"                    => $this->rg,
            "cpf"                   => $this->cpf,
            "data_nascimento"       => Carbon::parse($this->data_nascimento)->format('d/m/Y'),
            "sexo"                  => $this->sexo,
            "telefone_celular"      => $this->telefone_celular,
            "status"                => $this->status,
            "email"                 => $this->email,
            "signature_path"        => $this->signature_path,
            "signature_url"         => $this->signature_path ? ($request->getSchemeAndHttpHost() . '/storage/' . $this->signature_path) : null,
            "status_motivo"         => $this->status_motivo,
            "tentativas"            => $this->tentativas,
            "companies"             => $this->getCompaniesAsString(),
            "empresas"              => EmpresaResource::collection($this->companies),
            "permissao"             => $this->groups,

        ];
    }
}

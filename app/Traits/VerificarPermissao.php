<?php
namespace App\Traits;

trait VerificarPermissao
{
    public function contem($idCompany, $user, $permissao)
    {

        try {
            // Filtrar os grupos do usuário pela empresa
            $result = $user->groups->filter(function($item) use ($idCompany) {
                return $item['company_id'] == $idCompany;
            });

            // Verificar se o resultado do filtro não está vazio
            if ($result->isEmpty()) {
                return false;
            }

            // Acessar o primeiro item do resultado filtrado
            $group = $result->first();

            // Filtrar os itens do grupo pela permissão
            $filteredItems = $group->items->filter(function($item) use ($permissao) {
                return $item['slug'] == $permissao;
            });

            // Retornar se há itens filtrados
            return $filteredItems->isNotEmpty();

        } catch (\Exception $e) {
            return false;
        }
    }
}

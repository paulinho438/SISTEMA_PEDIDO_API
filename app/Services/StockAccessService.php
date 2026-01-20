<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockAccessService
{
    /**
     * Verifica se o usuário tem acesso a um local específico
     *
     * @param User $user
     * @param int $locationId
     * @param int $companyId
     * @return bool
     */
    public function canAccessLocation(User $user, int $locationId, int $companyId): bool
    {
        // Se usuário é Super Administrador, permite acesso a todos os locais
        $groupName = $user->getGroupNameByEmpresaId($companyId);
        if ($groupName === 'Super Administrador') {
            return true;
        }

        // Se usuário tem permissão administrativa, permite
        if ($user->hasPermission('admin_estoque') || $user->hasPermission('gerenciar_estoque')) {
            return true;
        }

        // Se usuário é supervisor, permite acesso a todos os locais
        if ($user->hasPermission('supervisor_estoque')) {
            return true;
        }

        // Se usuário é almoxarife ou está associado a locais, verifica associação
        // Verifica se está associado na tabela stock_almoxarife_locations
        $isAssociated = DB::table('stock_almoxarife_locations')
            ->where('user_id', $user->id)
            ->where('stock_location_id', $locationId)
            ->where('company_id', $companyId)
            ->exists();

        if ($user->hasPermission('almoxarife') || $isAssociated) {
            return $isAssociated;
        }

        return false;
    }

    /**
     * Retorna array com IDs dos locais que o usuário pode acessar
     *
     * @param User $user
     * @param int $companyId
     * @return array
     */
    public function getAccessibleLocationIds(User $user, int $companyId): array
    {
        // Se usuário é Super Administrador, retorna todos os locais (incluindo inativos)
        $groupName = $user->getGroupNameByEmpresaId($companyId);
        if ($groupName === 'Super Administrador') {
            return DB::table('stock_locations')
                ->where('company_id', $companyId)
                ->pluck('id')
                ->toArray();
        }

        // Se usuário tem permissão administrativa ou é supervisor, retorna todos os locais ativos
        if ($user->hasPermission('admin_estoque') || 
            $user->hasPermission('gerenciar_estoque') || 
            $user->hasPermission('supervisor_estoque')) {
            
            return DB::table('stock_locations')
                ->where('company_id', $companyId)
                ->where('active', true)
                ->pluck('id')
                ->toArray();
        }

        // Se usuário é almoxarife ou está associado a locais, retorna locais associados
        $associatedLocations = DB::table('stock_almoxarife_locations')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->pluck('stock_location_id')
            ->toArray();

        // Se tem permissão almoxarife ou está associado a locais, retorna os locais associados
        if ($user->hasPermission('almoxarife') || !empty($associatedLocations)) {
            return $associatedLocations;
        }

        return [];
    }

    /**
     * Aplica filtro de acesso aos locais em uma query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @param int $companyId
     * @param string $locationColumn
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyLocationFilter($query, User $user, int $companyId, string $locationColumn = 'stock_location_id')
    {
        $locationIds = $this->getAccessibleLocationIds($user, $companyId);

        if (empty($locationIds)) {
            // Se não tem acesso a nenhum local, retorna query vazia
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($locationColumn, $locationIds);
    }
}


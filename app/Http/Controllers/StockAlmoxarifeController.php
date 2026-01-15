<?php

namespace App\Http\Controllers;

use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StockAlmoxarifeController extends Controller
{
    public function listByLocation(Request $request, $locationId)
    {
        $companyId = $request->header('company-id');
        
        $location = StockLocation::where('id', $locationId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $almoxarifes = DB::table('stock_almoxarife_locations')
            ->where('stock_almoxarife_locations.stock_location_id', $locationId)
            ->where('stock_almoxarife_locations.company_id', $companyId)
            ->join('users', 'stock_almoxarife_locations.user_id', '=', 'users.id')
            ->select('users.id', 'users.nome_completo', 'users.email')
            ->get();

        return response()->json([
            'location' => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
            ],
            'almoxarifes' => $almoxarifes,
        ]);
    }

    public function listByAlmoxarife(Request $request, $userId)
    {
        $companyId = $request->header('company-id');
        
        $user = User::where('id', $userId)->firstOrFail();

        $locations = DB::table('stock_almoxarife_locations')
            ->where('stock_almoxarife_locations.user_id', $userId)
            ->where('stock_almoxarife_locations.company_id', $companyId)
            ->join('stock_locations', 'stock_almoxarife_locations.stock_location_id', '=', 'stock_locations.id')
            ->select('stock_locations.id', 'stock_locations.code', 'stock_locations.name')
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'nome_completo' => $user->nome_completo,
                'email' => $user->email,
            ],
            'locations' => $locations,
        ]);
    }

    public function associate(Request $request, $locationId)
    {
        $validator = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $companyId = $request->header('company-id');
        
        $location = StockLocation::where('id', $locationId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $user = User::findOrFail($validator['user_id']);

        // Verificar se usuário tem perfil Almoxarife na empresa
        $groupName = $user->getGroupNameByEmpresaId($companyId);
        if ($groupName !== 'Almoxarife') {
            return response()->json([
                'message' => 'Usuário deve ter perfil de Almoxarife.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Verificar se associação já existe
        $exists = DB::table('stock_almoxarife_locations')
            ->where('user_id', $user->id)
            ->where('stock_location_id', $locationId)
            ->where('company_id', $companyId)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Associação já existe.',
            ], Response::HTTP_CONFLICT);
        }

        DB::table('stock_almoxarife_locations')->insert([
            'user_id' => $user->id,
            'stock_location_id' => $locationId,
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Associação criada com sucesso.',
        ]);
    }

    public function disassociate(Request $request, $locationId, $userId)
    {
        $companyId = $request->header('company-id');

        DB::table('stock_almoxarife_locations')
            ->where('user_id', $userId)
            ->where('stock_location_id', $locationId)
            ->where('company_id', $companyId)
            ->delete();

        return response()->json([
            'message' => 'Associação removida com sucesso.',
        ]);
    }

    public function associateMultiple(Request $request, $userId)
    {
        $validator = $request->validate([
            'location_ids' => 'required|array',
            'location_ids.*' => 'exists:stock_locations,id',
        ]);

        $companyId = $request->header('company-id');
        
        $user = User::findOrFail($userId);

        // Verificar se usuário tem perfil Almoxarife na empresa
        $groupName = $user->getGroupNameByEmpresaId($companyId);
        if ($groupName !== 'Almoxarife') {
            return response()->json([
                'message' => 'Usuário deve ter perfil de Almoxarife.',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationIds = $validator['location_ids'];
        $inserts = [];

        foreach ($locationIds as $locationId) {
            // Verificar se local pertence à empresa
            $location = StockLocation::where('id', $locationId)
                ->where('company_id', $companyId)
                ->first();

            if (!$location) {
                continue;
            }

            // Verificar se associação já existe
            $exists = DB::table('stock_almoxarife_locations')
                ->where('user_id', $userId)
                ->where('stock_location_id', $locationId)
                ->where('company_id', $companyId)
                ->exists();

            if (!$exists) {
                $inserts[] = [
                    'user_id' => $userId,
                    'stock_location_id' => $locationId,
                    'company_id' => $companyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($inserts)) {
            DB::table('stock_almoxarife_locations')->insert($inserts);
        }

        return response()->json([
            'message' => 'Associações criadas com sucesso.',
        ]);
    }

    public function disassociateMultiple(Request $request, $userId)
    {
        $validator = $request->validate([
            'location_ids' => 'required|array',
            'location_ids.*' => 'exists:stock_locations,id',
        ]);

        $companyId = $request->header('company-id');
        $locationIds = $validator['location_ids'];

        DB::table('stock_almoxarife_locations')
            ->where('user_id', $userId)
            ->whereIn('stock_location_id', $locationIds)
            ->where('company_id', $companyId)
            ->delete();

        return response()->json([
            'message' => 'Associações removidas com sucesso.',
        ]);
    }

    public function listAlmoxarifes(Request $request)
    {
        $companyId = $request->header('company-id');
        
        // Buscar apenas usuários que têm o perfil "Almoxarife" na empresa
        $users = User::whereHas('companies', function($query) use ($companyId) {
            $query->where('id', $companyId);
        })
        ->whereHas('groups', function($query) use ($companyId) {
            $query->where('company_id', $companyId)
                  ->where('name', 'Almoxarife');
        })
        ->select('id', 'nome_completo', 'email')
        ->get();

        // Formatar resposta
        $users = $users->map(function($user) {
            return [
                'id' => $user->id,
                'nome_completo' => $user->nome_completo,
                'email' => $user->email,
                'has_permission_almoxarife' => true,
            ];
        });

        return response()->json($users);
    }
}


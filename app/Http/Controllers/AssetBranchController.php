<?php

namespace App\Http\Controllers;

use App\Models\AssetBranch;
use App\Http\Resources\AssetBranchResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetBranchController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->header('company-id');
        $query = AssetBranch::where('company_id', $companyId);

        // Aplicar filtro de ativo apenas se não for Super Administrador
        if (!$request->has('all') || !$request->boolean('all')) {
            $query->where('active', true);
        }

        return AssetBranchResource::collection($query->orderBy('name')->get());
    }

    public function show(Request $request, $id)
    {
        $item = AssetBranch::where('company_id', $request->header('company-id'))->findOrFail($id);
        return new AssetBranchResource($item);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $item = AssetBranch::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetBranchResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetBranch::findOrFail($id);
        $item->update($request->all());
        return new AssetBranchResource($item->fresh());
    }

    public function destroy($id)
    {
        AssetBranch::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


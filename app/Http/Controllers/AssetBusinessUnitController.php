<?php

namespace App\Http\Controllers;

use App\Models\AssetBusinessUnit;
use App\Http\Resources\AssetBusinessUnitResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetBusinessUnitController extends Controller
{
    public function index(Request $request)
    {
        $items = AssetBusinessUnit::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return AssetBusinessUnitResource::collection($items);
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

        $item = AssetBusinessUnit::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetBusinessUnitResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetBusinessUnit::findOrFail($id);
        $item->update($request->all());
        return new AssetBusinessUnitResource($item->fresh());
    }

    public function destroy($id)
    {
        AssetBusinessUnit::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


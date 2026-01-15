<?php

namespace App\Http\Controllers;

use App\Models\AssetGrouping;
use App\Http\Resources\AssetGroupingResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetGroupingController extends Controller
{
    public function index(Request $request)
    {
        $items = AssetGrouping::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return AssetGroupingResource::collection($items);
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

        $item = AssetGrouping::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetGroupingResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetGrouping::findOrFail($id);
        $item->update($request->all());
        return new AssetGroupingResource($item->fresh());
    }

    public function destroy($id)
    {
        AssetGrouping::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


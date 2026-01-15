<?php

namespace App\Http\Controllers;

use App\Models\AssetSubType1;
use App\Http\Resources\AssetSubType1Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetSubType1Controller extends Controller
{
    public function index(Request $request)
    {
        $items = AssetSubType1::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return AssetSubType1Resource::collection($items);
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

        $item = AssetSubType1::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetSubType1Resource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetSubType1::findOrFail($id);
        $item->update($request->all());
        return new AssetSubType1Resource($item->fresh());
    }

    public function destroy($id)
    {
        AssetSubType1::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\AssetSubType2;
use App\Http\Resources\AssetSubType2Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetSubType2Controller extends Controller
{
    public function index(Request $request)
    {
        $query = AssetSubType2::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->with('subType1');

        if ($request->filled('asset_sub_type_1_id')) {
            $query->where('asset_sub_type_1_id', $request->get('asset_sub_type_1_id'));
        }

        return AssetSubType2Resource::collection($query->orderBy('name')->get());
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

        $item = AssetSubType2::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetSubType2Resource($item->fresh()->load('subType1'));
    }

    public function update(Request $request, $id)
    {
        $item = AssetSubType2::findOrFail($id);
        $item->update($request->all());
        return new AssetSubType2Resource($item->fresh()->load('subType1'));
    }

    public function destroy($id)
    {
        AssetSubType2::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


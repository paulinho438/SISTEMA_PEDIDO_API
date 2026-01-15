<?php

namespace App\Http\Controllers;

use App\Models\AssetStandardDescription;
use App\Models\CustomLog;
use App\Http\Resources\AssetStandardDescriptionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetStandardDescriptionController extends Controller
{
    public function index(Request $request)
    {
        $items = AssetStandardDescription::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return AssetStandardDescriptionResource::collection($items);
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

        $item = AssetStandardDescription::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetStandardDescriptionResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetStandardDescription::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $item->update($request->all());

        return new AssetStandardDescriptionResource($item->fresh());
    }

    public function destroy($id)
    {
        $item = AssetStandardDescription::findOrFail($id);
        $item->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


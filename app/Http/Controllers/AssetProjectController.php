<?php

namespace App\Http\Controllers;

use App\Models\AssetProject;
use App\Http\Resources\AssetProjectResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetProjectController extends Controller
{
    public function index(Request $request)
    {
        $items = AssetProject::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return AssetProjectResource::collection($items);
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

        $item = AssetProject::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetProjectResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetProject::findOrFail($id);
        $item->update($request->all());
        return new AssetProjectResource($item->fresh());
    }

    public function destroy($id)
    {
        AssetProject::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


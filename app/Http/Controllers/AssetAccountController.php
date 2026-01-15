<?php

namespace App\Http\Controllers;

use App\Models\AssetAccount;
use App\Http\Resources\AssetAccountResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetAccountController extends Controller
{
    public function index(Request $request)
    {
        $items = AssetAccount::where('company_id', $request->header('company-id'))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return AssetAccountResource::collection($items);
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

        $item = AssetAccount::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetAccountResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetAccount::findOrFail($id);
        $item->update($request->all());
        return new AssetAccountResource($item->fresh());
    }

    public function destroy($id)
    {
        AssetAccount::findOrFail($id)->delete();
        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


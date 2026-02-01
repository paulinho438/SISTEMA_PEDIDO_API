<?php

namespace App\Http\Controllers;

use App\Models\AssetUseCondition;
use App\Models\CustomLog;
use App\Http\Resources\AssetUseConditionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AssetUseConditionController extends Controller
{
    protected $custom_log;

    public function __construct(CustomLog $custom_log)
    {
        $this->custom_log = $custom_log;
    }

    public function index(Request $request)
    {
        $query = AssetUseCondition::where('company_id', $request->header('company-id'));
        // Ao editar ativo, o front envia all=true para incluir inativos e exibir o valor já selecionado
        if (!$request->boolean('all')) {
            $query->where('active', true);
        }
        $items = $query->orderBy('name')->get();

        return AssetUseConditionResource::collection($items);
    }

    public function show($id)
    {
        $item = AssetUseCondition::findOrFail($id);
        return new AssetUseConditionResource($item);
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

        $item = AssetUseCondition::create([
            ...$request->all(),
            'company_id' => $request->header('company-id'),
        ]);

        return new AssetUseConditionResource($item);
    }

    public function update(Request $request, $id)
    {
        $item = AssetUseCondition::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $item->update($request->all());

        return new AssetUseConditionResource($item->fresh());
    }

    public function destroy($id)
    {
        $item = AssetUseCondition::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item excluído com sucesso.']);
    }
}


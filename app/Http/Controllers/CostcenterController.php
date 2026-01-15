<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Costcenter;
use App\Models\CustomLog;
use App\Models\User;

use App\Http\Resources\CostcenterResource;
use App\Services\CostcenterService;
use App\Exceptions\BusinessException;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class CostcenterController extends Controller
{

    protected $custom_log;

    public function __construct(CustomLog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function id(Request $r, $id){
        return new CostcenterResource(Costcenter::find($id));
    }

    public function all(Request $request, CostcenterService $costcenterService){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Centro de Custo',
            'operation' => 'index'
        ]);

        try {
            $costcenters = $costcenterService->getAllFromProtheus($request);
            
            // Transformar para o formato esperado pelo Resource
            $data = $costcenters->map(function ($item) {
                return (object) $item;
            });

            return CostcenterResource::collection($data);
        } catch (BusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            $dados['company_id'] = $request->header('company-id');

            $newGroup = Costcenter::create($dados);

            return $array;

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }

        return $array;
    }

    public function update(Request $request, $id){


        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'description' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $EditCostcenter = Costcenter::find($id);

                $EditCostcenter->name = $dados['name'];
                $EditCostcenter->description = $dados['description'];

                $EditCostcenter->save();

            } else {
                return response()->json([
                    "message" => $validator->errors()->first(),
                    "error" => $validator->errors()->first()
                ], Response::HTTP_FORBIDDEN);
            }

            DB::commit();

            return $array;

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                "message" => "Erro ao editar Centro de Custo.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }



    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $costCenter = Costcenter::withCount('emprestimos')->findOrFail($id);

            if ($costCenter->emprestimos_count > 0) {
                return response()->json([
                    "message" => "Centro de Custo ainda tem empréstimos associados."
                ], Response::HTTP_FORBIDDEN);
            }

            $costCenter->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou o Centro de Custo: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Centro de Custo excluído com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar o Centro de Custo: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir Centro de Custo.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

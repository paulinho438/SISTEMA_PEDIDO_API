<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Permgroup;
use App\Models\Permitem;
use App\Models\CustomLog;
use App\Models\User;
use App\Models\Planos;

use App\Http\Resources\GroupResource;
use App\Http\Resources\ItemsResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class PlanosController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function getAll(Request $request){

        return Planos::all();
    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'nome' => 'required',
            'preco' => 'required',
            'descricao' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){


            Planos::create($dados);

            return $array;

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => $validator->errors()->first()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function update(Request $request, $id){


        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'nome' => 'required',
                'preco' => 'required',
                'descricao' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $EditGroup = Planos::find($id);

                $EditGroup->nome = $dados['nome'];
                $EditGroup->preco = $dados['preco'];
                $EditGroup->descricao = $dados['descricao'];

                $EditGroup->save();

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
                "message" => "Erro ao editar permissão.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function id(Request $r, $id){
        $res = Planos::find($id);
        return $res;
    }

    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Planos::withCount('companies')->findOrFail($id);

            if ($permGroup->companies_count > 0) {
                return response()->json([
                    "message" => "Plano ainda tem empresas associadas."
                ], Response::HTTP_FORBIDDEN);
            }

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou o Plano: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Plano excluída com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar o Plano: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir plano.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Category;
use App\Models\CustomLog;
use App\Models\User;

use App\Http\Resources\CategoryResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class CategoryController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function id(Request $r, $id){
        return new CategoryResource(Category::find($id));
    }

    public function all(Request $request){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Categorias',
            'operation' => 'index'
        ]);

        return CategoryResource::collection(Category::where('company_id', $request->header('company-id'))->get());
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

            $newGroup = Category::create($dados);

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

                $EditCategory = Category::find($id);

                $EditCategory->name = $dados['name'];
                $EditCategory->description = $dados['description'];

                $EditCategory->save();

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
                "message" => "Erro ao editar categoria.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }



    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Category::findOrFail($id);

            if($permGroup->standard){
                return response()->json([
                    "message" => "Não é possivel excluir uma Categoria padrão do sistema.",
                ], Response::HTTP_FORBIDDEN);
            }

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou a categoria: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Categoria excluída com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar a cattegoria: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir categoria.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

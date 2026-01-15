<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\Feriado;
use App\Models\Parcela;
use App\Models\CustomLog;
use App\Models\User;

use DateTime;
use App\Http\Resources\FeriadoResource;
use App\Http\Resources\ParcelaResource;
use Illuminate\Support\Carbon;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class FeriadoController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function id(Request $r, $id){
        return new FeriadoResource(Feriado::find($id));
    }

    public function all(Request $request){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Feriados',
            'operation' => 'index'
        ]);

        return FeriadoResource::collection(Feriado::where('company_id', $request->header('company-id'))->orderBy('id', 'desc')->get());

    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'description' => 'required',
            'data_feriado' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            $dados['company_id'] = $request->header('company-id');
            $dados['data_feriado'] = (DateTime::createFromFormat('d/m/Y', $dados['data_feriado']))->format('Y-m-d');

            $newGroup = Feriado::create($dados);
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
                'description' => 'required',
                'data_feriado' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $EditFeriado = Feriado::find($id);

                $EditFeriado['description'] = $dados['description'];
                $EditFeriado['data_feriado'] = (DateTime::createFromFormat('d/m/Y', $dados['data_feriado']))->format('Y-m-d');
                $EditFeriado->save();

            } else {
                return response()->json([
                    "message" => $validator->errors()->first(),
                    "error" => $validator->errors()->first()
                ], Response::HTTP_FORBIDDEN);
            }

            DB::commit();

            return $EditFeriado;

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                "message" => "Erro ao editar Feriado.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }



    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Feriado::findOrFail($id);

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou o Feriado: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Feriado excluído com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar o Feriado: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir Feriadoe.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

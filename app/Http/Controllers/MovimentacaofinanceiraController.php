<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\Movimentacaofinanceira;
use App\Models\CustomLog;
use App\Models\User;

use DateTime;
use App\Http\Resources\MovimentacaofinanceiraResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class MovimentacaofinanceiraController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function id(Request $r, $id){
        return new MovimentacaofinanceiraResource(Movimentacaofinanceira::find($id));
    }

    public function all(Request $request){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Contas a Receber',
            'operation' => 'index'
        ]);

        $dt_inicio = $request->query('dt_inicio');
        $dt_final = $request->query('dt_final');


        return MovimentacaofinanceiraResource::collection(
            Movimentacaofinanceira::where('company_id', $request->header('company-id'))
                ->whereBetween('dt_movimentacao', [$dt_inicio, $dt_final])
                ->orderBy('id', 'desc')
                ->get()
        );
    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'banco_id' => 'required',
            'client_id' => 'required',
            'status' => 'required',
            'tipodoc' => 'required',
            'descricao' => 'required',
            'lanc' => 'required',
            'venc' => 'required',
            'valor' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            $dados['company_id'] = $request->header('company-id');
            $dados['lanc'] = (DateTime::createFromFormat('d/m/Y', $dados['lanc']))->format('Y-m-d');
            $dados['venc'] = (DateTime::createFromFormat('d/m/Y', $dados['venc']))->format('Y-m-d');

            $newGroup = Movimentacaofinanceira::create($dados);

            return $array;

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => $validator->errors()->first()
            ], Response::HTTP_FORBIDDEN);
        }

        return $array;
    }


    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Movimentacaofinanceira::findOrFail($id);

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou o Contas a Receber: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Contas a Receber excluída com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar o Contas a Receber: '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir Contas a Receber.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\CustomLog;
use App\Models\User;

use App\Http\Resources\AddressResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class AddressController extends Controller
{

    protected $customLog;

    public function __construct(Customlog $customLog){
        $this->custom_log = $customLog;
    }

    public function id(Request $r, $id){
        return new AddressResource(Address::find($id));
    }

    public function all(Request $r, $id){
        return AddressResource::collection(Address::where('client_id', $id)->get());
    }

    public function insert(Request $request){
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'description' => 'required',
            'address' => 'required',
            'cep' => 'required',
            'number' => 'required',
            'complement' => 'required',
            'neighborhood' => 'required',
            'city' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $dados = $request->all();
        if(!$validator->fails()){

            Address::create($dados);

            return $array;

        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function cancelarCadastro() {
        Address::whereNull('cliente_id')->forceDelete();
        return true;
    }

    public function update(Request $request, $id){


        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $validator = Validator::make($request->all(), [
                'description' => 'required',
                'address' => 'required',
                'cep' => 'required',
                'number' => 'required',
                'complement' => 'required',
                'neighborhood' => 'required',
                'city' => 'required',
                'latitude' => 'required',
                'longitude' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $editAddress = Address::find($id);

                $editAddress->description   = $dados['description'];
                $editAddress->address       = $dados['address'];
                $editAddress->cep           = $dados['cep'];
                $editAddress->number        = $dados['number'];
                $editAddress->complement    = $dados['complement'];
                $editAddress->neighborhood  = $dados['neighborhood'];
                $editAddress->city          = $dados['city'];
                $editAddress->latitude      = $dados['latitude'];
                $editAddress->longitude     = $dados['longitude'];

                $editAddress->save();

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
                "message" => "Erro ao editar Endereço.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Address::findOrFail($id);

            $permGroup->delete();

            DB::commit();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' deletou o Endereço: '.$id,
                'operation' => 'destroy'
            ]);

            return response()->json(['message' => 'Endereço excluída com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->custom_log->create([
                'user_id' => auth()->user()->id,
                'content' => 'O usuário: '.auth()->user()->nome_completo.' tentou deletar o Endereço : '.$id.' ERROR: '.$e->getMessage(),
                'operation' => 'error'
            ]);

            return response()->json([
                "message" => "Erro ao excluir endereço.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }
}

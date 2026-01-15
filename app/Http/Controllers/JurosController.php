<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Juros;
use App\Models\CustomLog;
use App\Models\User;

use App\Http\Resources\JurosResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class JurosController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }

    public function get(Request $request){
        return Juros::select('juros')->where('company_id', $request->header('company-id'))->first();
    }

    public function update(Request $request){


        DB::beginTransaction();

        try {
            $array = ['error' => ''];

            $user = auth()->user();

            $validator = Validator::make($request->all(), [
                'juros' => 'required',
            ]);

            $dados = $request->all();
            if(!$validator->fails()){

                $EditJuros = Juros::where('company_id', $request->header('company-id'))->first();

                $EditJuros->juros = $dados['juros'];

                $EditJuros->save();

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
                "message" => "Erro ao editar o Juros.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

}

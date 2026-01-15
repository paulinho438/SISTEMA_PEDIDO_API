<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Notas;
use App\Models\Deposito;
use App\Models\CustomLog;
use App\Models\Parcela;
use App\Models\Movimentacaofinanceira;

use App\Http\Resources\BancosResource;
use App\Http\Resources\BancosComSaldoResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\ControleBcodex;

use App\Services\BcodexService;

class NotasController extends Controller
{

    protected $custom_log;

    protected $bcodexService;

    public function __construct(Customlog $custom_log, BcodexService $bcodexService)
    {
        $this->custom_log = $custom_log;
        $this->bcodexService = $bcodexService;
    }

    public function id(Request $r, $id)
    {
        return Notas::find($id);
    }

    public function all(Request $r, $id)
    {
        return Notas::where('emprestimo_id', $id)->get();
    }

    public function insert(Request $request)
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'conteudo' => 'required',
            'emprestimo_id' => 'required'
        ]);

        $dados = $request->all();
        if (!$validator->fails()) {

            $newGroup = Notas::create($dados);

            return $newGroup;
        } else {
            return response()->json([
                "message" => $validator->errors()->first(),
                "error" => ""
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function delete(Request $r, $id)
    {
        DB::beginTransaction();

        try {
            $permGroup = Notas::findOrFail($id);

            $permGroup->delete();

            DB::commit();

            return response()->json(['message' => 'Nota excluído com sucesso.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                "message" => "Erro ao excluir Nota.",
                "error" => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }
    }

}

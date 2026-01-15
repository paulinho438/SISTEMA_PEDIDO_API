<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Address;
use App\Models\ControleBcodex;
use App\Models\CustomLog;
use App\Models\User;

use DateTime;
use App\Http\Resources\MovimentacaofinanceiraResource;

use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;
class ControleBcodexController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log){
        $this->custom_log = $custom_log;
    }



    public function all(Request $request){

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: '.auth()->user()->nome_completo.' acessou a tela de Controle Bcodex',
            'operation' => 'index'
        ]);

        $dt_inicio = Carbon::parse($request->query('dt_inicio'))->startOfDay();
        $dt_final = Carbon::parse($request->query('dt_final'))->endOfDay();


        $itensGeradoNaoPago = ControleBcodex::select(
            DB::raw('COUNT(*) * 0.04 AS total_registros_valor'),
            DB::raw('COUNT(*) AS total_registros')
        )
        ->whereNull('data_pagamento')
        ->whereBetween('created_at', [$dt_inicio, $dt_final])
        ->first();

        $itensGeradoPago = ControleBcodex::select(
            DB::raw('COUNT(*) * 0.30 AS total_registros_valor'),
            DB::raw('COUNT(*) AS total_registros')
        )
        ->whereNotNull('data_pagamento')
        ->whereBetween('created_at', [$dt_inicio, $dt_final])
        ->first();

        return [
            'itens_pagos' => $itensGeradoPago,
            'itens_nao_pagos' => $itensGeradoNaoPago
        ];
    }


}

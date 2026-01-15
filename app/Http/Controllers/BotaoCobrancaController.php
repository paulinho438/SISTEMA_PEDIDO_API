<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\CustomLog;
use App\Models\BotaoCobranca;

class BotaoCobrancaController extends Controller
{

    protected $custom_log;

    public function __construct(Customlog $custom_log)
    {
        $this->custom_log = $custom_log;
    }

    public function pressed(Request $request)
    {

        $this->custom_log->create([
            'user_id' => auth()->user()->id,
            'content' => 'O usuário: ' . auth()->user()->nome_completo . ' clicou no botao de cobranca',
            'operation' => 'index'
        ]);

        $botao = BotaoCobranca::where('company_id', $request->header('Company_id'))->first();

        if($botao && $botao->is_active) {
            return response()->json([
                'message' => 'Botão já foi pressionado'
            ], 400);
        }

        if($botao && !$botao->is_active && $botao->click_count == 3 && $botao->updated_at->format('Y-m-d') == now()->format('Y-m-d')) {
            return response()->json([
                'message' => 'Botão já foi pressionado o maximo de vezes permitido'
            ], 400);
        }

        if ($botao && $botao->updated_at->format('Y-m-d') != now()->format('Y-m-d')) {
            $botao->update([
                'is_active' => true,
                'click_count' => 1
            ]);
            return true;
        }

        if (!$botao) {
            $botao = BotaoCobranca::create([
                'is_active' => true,
                'click_count' => 1,
                'company_id' => $request->header('Company_id')
            ]);
            return true;
        } else {
            $botao->update([
                'is_active' => true,
                'click_count' => $botao->click_count + 1
            ]);
            return true;
        };
    }

    public function getButtonPressed(Request $request) {
        $botao = BotaoCobranca::where('company_id', $request->header('Company_id'))->first();

        return response()->json($botao);

    }
}

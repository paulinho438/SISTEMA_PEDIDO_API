<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WebhookTesteController extends Controller
{
    public function receber(Request $request)
    {
        // Loga a hora e o IP da requisição para análise posterior
        Log::info('Webhook recebido em: ' . now() . ' de ' . $request->ip());

        // Simula um pequeno processamento
        usleep(100 * 1000); // 100ms (para simular latência)

        return response()->json(['status' => 'ok', 'received_at' => now()]);
    }
}

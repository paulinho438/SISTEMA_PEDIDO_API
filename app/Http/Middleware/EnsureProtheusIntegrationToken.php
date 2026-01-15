<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProtheusIntegrationToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.protheus.token');

        if (!$expectedToken) {
            return response()->json([
                'message' => 'Token de integração com Protheus não configurado.',
            ], Response::HTTP_FORBIDDEN);
        }

        $provided = (string) $request->header('X-Integration-Token', '');

        if (!hash_equals($expectedToken, $provided)) {
            return response()->json([
                'message' => 'Token de integração inválido.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}


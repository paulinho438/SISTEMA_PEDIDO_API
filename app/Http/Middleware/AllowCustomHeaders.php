<?php

namespace App\Http\Middleware;

use Closure;

class AllowCustomHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Adiciona o cabeçalho Access-Control-Allow-Headers para permitir todos os cabeçalhos personalizados
        $response = $next($request);
        $response->headers->set('Access-Control-Allow-Headers', '*');
        $response->headers->set('Access-Control-Allow-Credentials', '*');

        return $response;
    }
}

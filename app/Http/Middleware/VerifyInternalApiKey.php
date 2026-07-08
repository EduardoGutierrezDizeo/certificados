<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenRecibido = $request->header('X-Internal-Api-Key');
        $tokenEsperado = config('services.internal_api.key');

        if (! $tokenEsperado || ! hash_equals($tokenEsperado, (string) $tokenRecibido)) {
            abort(401, 'No autorizado');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->hasRole('abogado')
            && $user->terms_version_accepted !== config('legal.terms_version')
            && ! $request->routeIs('legal.accept*')
            && ! $request->routeIs('legal.terms')
            && ! $request->routeIs('legal.privacy')
        ) {
            return redirect()->route('legal.accept');
        }

        return $next($request);
    }
}

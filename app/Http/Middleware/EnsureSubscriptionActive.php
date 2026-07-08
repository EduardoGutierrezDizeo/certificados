<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('abogado') && ! $user->hasActiveSubscription()
            && ! $request->routeIs('subscription.*')) {
            return redirect()->route('subscription.show');
        }

        return $next($request);
    }
}

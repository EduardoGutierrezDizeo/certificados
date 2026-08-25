<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('abogado')) {
            $currentSessionId = session()->getId();

            if ($user->current_session_id !== null && $user->current_session_id !== $currentSessionId) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->is('session/*')) {
                    return response()->json(['session_closed' => true], 401);
                }

                session()->flash('session_closed_elsewhere', true);

                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if ($user->hasRole('abogado') && $this->hasActiveSession($user)) {
            $forceToken = Crypt::encryptString(json_encode([
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(3)->timestamp,
            ]));

            return redirect()->route('login')
                ->with('sessionConflict', true)
                ->with('force_token', $forceToken)
                ->with('conflict_email', $request->string('email'))
                ->withInput($request->only('remember'));
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $request->user()->update([
            'current_session_id' => $request->session()->getId(),
        ]);

        if ($request->user()->hasRole('admin')) {
            return redirect()->route('admin.lawyers.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Force login after user confirms session conflict.
     */
    public function forceLogin(Request $request): RedirectResponse
    {
        $request->validate([
            'force_token' => ['required', 'string'],
        ]);

        try {
            $payload = json_decode(Crypt::decryptString((string) $request->input('force_token')), true);
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->withErrors(['email' => 'El enlace de confirmación ha expirado o es inválido. Intente iniciar sesión nuevamente.']);
        }

        if (! isset($payload['expires_at']) || $payload['expires_at'] < now()->timestamp) {
            return redirect()->route('login')
                ->withErrors(['email' => 'El enlace de confirmación ha expirado. Intente iniciar sesión nuevamente.']);
        }

        $user = User::find($payload['user_id']);

        if (! $user || ! $user->hasRole('abogado')) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Usuario no válido.']);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $user->update([
            'current_session_id' => $request->session()->getId(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->update([
            'current_session_id' => null,
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Determine if the user has an active (non-expired) session.
     */
    private function hasActiveSession(User $user): bool
    {
        if (! $user->current_session_id) {
            return false;
        }

        $session = DB::table('sessions')
            ->where('id', $user->current_session_id)
            ->first();

        if (! $session) {
            return false;
        }

        $lifetimeMinutes = (int) config('session.lifetime', 120);
        $expiresAt = $session->last_activity + ($lifetimeMinutes * 60);

        return $expiresAt > now()->timestamp;
    }
}

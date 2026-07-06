<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ForcePasswordController extends Controller
{
    public function edit()
    {
        return view('auth.force-password');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('status', 'Contraseña actualizada correctamente.');
    }
}
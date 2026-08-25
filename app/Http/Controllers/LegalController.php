<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function show(Request $request): View
    {
        $isFirstTime = is_null($request->user()->terms_version_accepted);

        return view('legal.accept', [
            'isFirstTime' => $isFirstTime,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->update([
            'terms_accepted_at' => now(),
            'terms_version_accepted' => config('legal.terms_version'),
        ]);

        return redirect()->route('dashboard');
    }
}

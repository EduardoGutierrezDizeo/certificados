<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LawyerController extends Controller
{
    public function index()
    {
        $lawyers = User::role('abogado')
            ->with(['subscriptions' => fn ($q) => $q->latest()->limit(1)])
            ->latest()
            ->paginate(15);

        return view('admin.lawyers.index', compact('lawyers'));
    }

    public function create()
    {
        return view('admin.lawyers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'plan' => ['required', 'string', 'max:100'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:36'],
        ]);

        $temporaryPassword = Str::password(12);

        $lawyer = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($temporaryPassword),
            'must_change_password' => true,
        ]);

        $lawyer->assignRole('abogado');

        $lawyer->subscriptions()->create([
            'plan' => $validated['plan'],
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths((int) $validated['duration_months']),
        ]);

        return redirect()
            ->route('admin.lawyers.index')
            ->with('generated_password', $temporaryPassword)
            ->with('generated_email', $lawyer->email);
    }
}

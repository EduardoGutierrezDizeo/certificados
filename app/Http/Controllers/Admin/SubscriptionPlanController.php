<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderByRaw('is_active = 1 DESC, created_at DESC')
            ->paginate(15);

        return view('admin.subscription-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.subscription-plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_in_cents' => ['required', 'integer', 'min:0'],
            'duration_months' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        SubscriptionPlan::create([
            'name' => $validated['name'],
            'price_in_cents' => $validated['price_in_cents'] * 100,
            'duration_months' => $validated['duration_months'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.subscription-plans.index')
            ->with('success', 'Plan creado correctamente.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return view('admin.subscription-plans.edit', ['plan' => $subscriptionPlan]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_in_cents' => ['required', 'integer', 'min:0'],
            'duration_months' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $subscriptionPlan->update([
            'name' => $validated['name'],
            'price_in_cents' => $validated['price_in_cents'] * 100,
            'duration_months' => $validated['duration_months'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('admin.subscription-plans.index')
            ->with('success', 'Plan actualizado correctamente.');
    }
}

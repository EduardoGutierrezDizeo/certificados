<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LawyerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::role('abogado')
            ->with(['subscriptions' => fn ($q) => $q->latest()->limit(1)]);

        if ($request->query('subscription') === 'active') {
            $query->whereHas('subscriptions', fn ($q) => $q
                ->where('status', 'active')
                ->where('ends_at', '>=', now()),
            );
        }

        $lawyers = $query->latest()->paginate(15);

        return view('admin.lawyers.index', compact('lawyers'));
    }

    public function create()
    {
        $plans = SubscriptionPlan::active()->get();

        return view('admin.lawyers.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::where('id', $validated['subscription_plan_id'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return back()->withErrors([
                'subscription_plan_id' => 'El plan seleccionado no está activo.',
            ])->withInput();
        }

        $temporaryPassword = Str::password(12);

        $lawyer = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($temporaryPassword),
            'must_change_password' => true,
        ]);

        $lawyer->assignRole('abogado');

        $lawyer->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'plan' => $plan->name,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths($plan->duration_months),
        ]);

        Payment::create([
            'user_id' => $lawyer->id,
            'subscription_plan_id' => $plan->id,
            'reference' => 'ADMIN-GRANT-'.$lawyer->id.'-'.Str::random(10),
            'payment_provider' => 'admin_grant',
            'amount_in_cents' => 0,
            'status' => 'approved',
            'raw_payload' => [
                'granted_by_admin_id' => $request->user()->id,
                'reason' => 'Cuenta creada manualmente por administrador',
            ],
        ]);

        return redirect()
            ->route('admin.lawyers.index')
            ->with('generated_password', $temporaryPassword)
            ->with('generated_email', $lawyer->email);
    }

    public function suspendSubscription(User $lawyer)
    {
        $subscription = $lawyer->subscriptions()->where('status', 'active')->latest()->first();
        abort_unless($subscription, 404, 'Este abogado no tiene una suscripción activa para suspender.');

        $subscription->update(['status' => 'suspended']);

        return back()->with('success', "Suscripción de {$lawyer->name} suspendida.");
    }

    public function reactivateSubscription(User $lawyer)
    {
        $subscription = $lawyer->subscriptions()
            ->where('status', 'suspended')
            ->where('ends_at', '>=', now())
            ->latest()
            ->first();

        abort_unless($subscription, 404, 'No hay una suscripción suspendida y vigente para reactivar.');

        $subscription->update(['status' => 'active']);

        return back()->with('success', "Suscripción de {$lawyer->name} reactivada.");
    }

    public function cancelSubscription(User $lawyer)
    {
        $subscription = $lawyer->subscriptions()
            ->whereIn('status', ['active', 'suspended'])
            ->latest()
            ->first();

        abort_unless($subscription, 404, 'No hay una suscripción activa o suspendida para cancelar.');

        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', "Suscripción de {$lawyer->name} cancelada.");
    }

    public function payments(User $lawyer)
    {
        $payments = $lawyer->payments()->latest()->paginate(15);

        return view('admin.lawyers.payments', compact('lawyer', 'payments'));
    }
}

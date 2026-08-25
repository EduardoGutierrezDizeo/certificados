<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(): View
    {
        $plans = SubscriptionPlan::active()->get();

        return view('subscription.show', ['plans' => $plans]);
    }

    public function manage(): View
    {
        $subscription = auth()->user()->currentSubscription();

        return view('subscription.manage', ['subscription' => $subscription]);
    }

    public function cancelSubscription(): RedirectResponse
    {
        $user = auth()->user();
        $subscription = $user->currentSubscription();

        if (! $subscription || ! $subscription->isActive()) {
            return back()->withErrors(['subscription' => 'No tienes una suscripción activa para cancelar.']);
        }

        $subscription->cancel();

        return redirect()->route('subscription.manage')
            ->with('success', 'Tu suscripción fue cancelada. Ya no tienes acceso a las funciones premium.');
    }

    public function checkout(SubscriptionPlan $subscriptionPlan): View
    {
        $reference = 'CERTICHECK-'.auth()->id().'-'.Str::random(10);

        Payment::create([
            'user_id' => auth()->id(),
            'subscription_plan_id' => $subscriptionPlan->id,
            'reference' => $reference,
            'payment_provider' => 'epayco',
            'amount_in_cents' => $subscriptionPlan->price_in_cents,
            'status' => 'pending',
        ]);

        return view('subscription.checkout', [
            'amount' => $subscriptionPlan->price_in_cents / 100,
            'reference' => $reference,
            'planName' => $subscriptionPlan->name,
            'publicKey' => config('services.epayco.public_key'),
            'testMode' => config('services.epayco.test_mode'),
        ]);
    }

    public function return()
    {
        $queryParams = request()->query();

        Log::info('Retorno de checkout ePayco', $queryParams);

        $response = $queryParams['x_response'] ?? $queryParams['response'] ?? null;
        $transactionState = $queryParams['x_transaction_state'] ?? $queryParams['x_cod_transaction_state'] ?? null;

        $estadosFallidos = ['Rechazada', 'Fallida', 'Cancelada', 'Cancelado', 'Declined', 'Failed'];

        $pareceFallido = $response && in_array($response, $estadosFallidos, true);

        return view('subscription.return', [
            'pareceFallido' => $pareceFallido,
            'estadoRecibido' => $response,
        ]);
    }

    public function status()
    {
        return response()->json([
            'active' => auth()->user()->hasActiveSubscription(),
        ]);
    }
}

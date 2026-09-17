<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(): View
    {
        $plans = SubscriptionPlan::active()->get();

        return view('subscription.show', [
            'plans' => $plans,
            'pendingPayment' => auth()->user()->payments()->pendingVigente()->first(),
        ]);
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

    public function checkout(SubscriptionPlan $subscriptionPlan): View|RedirectResponse|JsonResponse
    {
        $pendingVigente = auth()->user()->payments()->pendingVigente()->first();

        if ($pendingVigente) {
            $mensaje = 'Ya tienes un pago en proceso. Te avisaremos por correo en cuanto se confirme.';

            if (request()->expectsJson()) {
                return response()->json(['message' => $mensaje], 409);
            }

            return back()->withErrors(['subscription' => $mensaje]);
        }

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
        $estadosPendientes = ['Pendiente', 'Iniciada'];

        $pareceFallido = $response && in_array($response, $estadosFallidos, true);
        $esPendiente = in_array($transactionState, $estadosPendientes, true)
            || in_array($response, $estadosPendientes, true);

        if ($esPendiente) {
            $estadoInicial = 'pendingConfirmation';
        } elseif ($pareceFallido) {
            $estadoInicial = 'failed';
        } else {
            $estadoInicial = 'checking';
        }

        return view('subscription.return', [
            'pareceFallido' => $pareceFallido,
            'estadoRecibido' => $response,
            'estadoInicial' => $estadoInicial,
            'reference' => $queryParams['x_id_factura'] ?? $queryParams['reference'] ?? null,
        ]);
    }

    public function status(): JsonResponse
    {
        $reference = request()->query('reference');

        if ($reference) {
            $payment = Payment::query()
                ->where('user_id', auth()->id())
                ->where('reference', $reference)
                ->first();

            return response()->json([
                'active' => auth()->user()->hasActiveSubscription(),
                'payment_status' => $payment?->status,
            ]);
        }

        return response()->json([
            'active' => auth()->user()->hasActiveSubscription(),
        ]);
    }
}

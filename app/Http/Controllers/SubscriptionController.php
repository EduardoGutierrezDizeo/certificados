<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show()
    {
        $priceInCents = ((int) config('services.subscription_price_cop', 50000)) * 100;

        return view('subscription.show', ['priceInCents' => $priceInCents]);
    }

    public function checkout()
    {
        $priceInCents = ((int) config('services.subscription_price_cop', 50000)) * 100;
        $reference = 'CERTICHECK-'.auth()->id().'-'.Str::random(10);

        Payment::create([
            'user_id' => auth()->id(),
            'reference' => $reference,
            'payment_provider' => 'epayco',
            'amount_in_cents' => $priceInCents,
            'status' => 'pending',
        ]);

        return view('subscription.checkout', [
            'amount' => $priceInCents / 100,
            'reference' => $reference,
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

    public function paymentHistory(): View
    {
        $payments = auth()->user()->payments()->latest()->paginate(15);

        return view('subscription.history', compact('payments'));
    }
}

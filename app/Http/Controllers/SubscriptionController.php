<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\WompiSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function show()
    {
        $priceInCents = ((int) config('services.subscription_price_cop', 50000)) * 100;

        return view('subscription.show', ['priceInCents' => $priceInCents]);
    }

    public function checkout(WompiSignatureService $signer)
    {
        $priceInCents = ((int) config('services.subscription_price_cop', 50000)) * 100;
        $reference = 'CERTICHECK-'.auth()->id().'-'.Str::random(10);

        Payment::create([
            'user_id' => auth()->id(),
            'reference' => $reference,
            'amount_in_cents' => $priceInCents,
            'status' => 'pending',
        ]);

        $signature = $signer->integritySignature($reference, $priceInCents);

        $params = [
            'public-key' => config('services.wompi.public_key'),
            'currency' => 'COP',
            'amount-in-cents' => $priceInCents,
            'reference' => $reference,
            'signature:integrity' => $signature,
            'redirect-url' => route('subscription.return'),
        ];

        $queryString = collect($params)
            ->map(fn ($value, $key) => $key.'='.rawurlencode($value))
            ->implode('&');

        return redirect("https://checkout.wompi.co/p/?{$queryString}");
    }

    public function return()
    {
        return view('subscription.return');
    }

    public function status()
    {
        return response()->json([
            'active' => auth()->user()->hasActiveSubscription(),
        ]);
    }
}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Procesando pago</h2>
    </x-slot>

    <div class="max-w-lg">
        <div class="bg-white border border-ink-100 rounded-lg p-8 text-center">
            <svg class="mx-auto h-10 w-10 animate-spin text-brass-500 mb-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <p class="text-sm text-carbon/70">Abriendo la pasarela de pago segura...</p>
        </div>
    </div>

    <script src="https://checkout.epayco.co/checkout.js"></script>
    <script>
        const handler = ePayco.checkout.configure({
            key: '{{ $publicKey }}',
            test: {{ $testMode ? 'true' : 'false' }},
        });

        const data = {
            name: 'Suscripción CertiCheck - Plan Standard',
            description: 'Suscripción mensual CertiCheck',
            invoice: '{{ $reference }}',
            currency: 'cop',
            amount: '{{ $amount }}',
            tax_base: '0',
            tax: '0',
            country: 'co',
            lang: 'es',
            external: 'false',
            response: '{{ route('subscription.return') }}',
            confirmation: '{{ route('webhooks.epayco') }}',
            methodsDisable: [],
        };

        handler.open(data);
    </script>
</x-app-layout>

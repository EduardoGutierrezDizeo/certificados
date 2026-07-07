<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Suscripción</h2>
    </x-slot>

    <div class="max-w-lg">
        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <div class="h-1 bg-brass"></div>
            <div class="p-8 text-center">
                <div class="mx-auto h-14 w-14 rounded-full border-2 border-ink-100 flex items-center justify-center mb-5">
                    <x-application-logo class="h-7 w-7 text-ink-700" />
                </div>

                <h3 class="font-serif text-xl text-ink-700 mb-1">Plan Standard</h3>
                <p class="text-sm text-carbon/60 mb-6">Acceso completo a los 4 certificados, por un mes.</p>

                <p class="font-serif text-4xl text-ink-700 mb-1">
                    ${{ number_format($priceInCents / 100, 0, ',', '.') }}
                    <span class="text-base font-sans text-carbon/50 font-normal">COP / mes</span>
                </p>

                <a href="{{ route('subscription.checkout') }}"
                   class="mt-8 inline-flex items-center justify-center gap-2 w-full bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3.5 rounded-md transition">
                    Pagar con PSE
                </a>

                <p class="text-xs text-carbon/40 mt-4">
                    Serás redirigido a Wompi, nuestra pasarela de pagos segura.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>

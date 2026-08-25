<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Suscripción</h2>
    </x-slot>

    <div class="max-w-3xl space-y-5">
        @forelse ($plans as $plan)
            <div class="bg-white border border-ink-100 rounded-lg overflow-hidden flex flex-col sm:flex-row">
                <div class="hidden sm:block w-1.5 bg-brass shrink-0"></div>
                <div class="h-1.5 sm:hidden bg-brass"></div>
                <div class="flex-1 p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-serif text-xl text-ink-700 mb-1">{{ $plan->name }}</h3>
                        <p class="font-serif text-3xl text-ink-800 tracking-tight">
                            ${{ number_format($plan->price_in_cents / 100, 0, ',', '.') }}
                            <span class="text-sm font-sans text-carbon/50 font-normal tracking-normal">
                                COP
                            </span>
                        </p>
                        <p class="text-sm text-carbon/50 mt-1">
                            {{ $plan->duration_months === 1 ? 'Mensual' : "{$plan->duration_months} meses" }}
                        </p>
                        @if ($plan->description)
                            <p class="text-sm text-carbon/60 mt-2 leading-relaxed">{{ $plan->description }}</p>
                        @endif
                    </div>
                    <a href="{{ route('subscription.checkout', $plan) }}"
                       class="inline-flex items-center justify-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition shrink-0 self-start">
                        Pagar con PSE
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white border border-ink-100 rounded-lg p-8 text-center">
                <p class="text-sm text-carbon/60">No hay planes disponibles en este momento. Contacta al administrador.</p>
            </div>
        @endforelse
    </div>
</x-app-layout>

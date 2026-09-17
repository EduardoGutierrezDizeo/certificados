<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Suscripción</h2>
    </x-slot>

    <div class="max-w-3xl space-y-5">
        @if ($pendingPayment)
            <div class="bg-brass-50 border border-brass/40 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-ink-700">
                        Ya tienes un pago en proceso desde {{ $pendingPayment->created_at->diffForHumans() }}.
                    </p>
                    <p class="text-xs text-carbon/60 mt-0.5">
                        Te avisaremos por correo en cuanto se confirme. No es necesario que pagues de nuevo.
                    </p>
                </div>
                <button type="button"
                        x-data="pendingPaymentStatus('{{ $pendingPayment->reference }}')"
                        @click="check()"
                        class="inline-flex items-center justify-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-4 py-2 rounded-md transition shrink-0">
                    <span x-show="!checking">Comprobar estado</span>
                    <span x-show="checking" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Consultando...
                    </span>
                </button>
            </div>
        @endif

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
                    @if ($pendingPayment)
                        <span class="inline-flex items-center justify-center gap-2 bg-carbon/10 text-carbon/40 text-sm font-medium px-6 py-3 rounded-md cursor-not-allowed shrink-0 self-start">
                            Pago en proceso
                        </span>
                    @else
                        <a href="{{ route('subscription.checkout', $plan) }}"
                           class="inline-flex items-center justify-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition shrink-0 self-start">
                            Pagar con PSE
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="bg-white border border-ink-100 rounded-lg p-8 text-center">
                <p class="text-sm text-carbon/60">No hay planes disponibles en este momento. Contacta al administrador.</p>
            </div>
        @endforelse
    </div>

    <script>
        function pendingPaymentStatus(reference) {
            return {
                checking: false,

                async check() {
                    if (this.checking) return;
                    this.checking = true;
                    try {
                        const url = new URL('{{ route('subscription.status') }}');
                        url.searchParams.set('reference', reference);
                        const data = await (await fetch(url)).json();

                        if (data.active) {
                            const result = await swalSuccess({
                                title: '¡Pago confirmado!',
                                text: 'Tu suscripción ya está activa. Te llevaremos al panel.',
                                confirmButtonText: 'Ir al panel',
                            });
                            if (result.isConfirmed) {
                                window.location.href = '{{ route('dashboard') }}';
                            }
                            return;
                        }

                        if (data.payment_status === 'declined' || data.payment_status === 'error' || data.payment_status === 'voided') {
                            const result = await swalConfirm({
                                title: 'El pago no se completó',
                                text: 'Puedes intentarlo de nuevo desde la lista de planes.',
                                icon: 'warning',
                                confirmButtonText: 'Ver planes',
                                cancelButtonText: 'Ahora no',
                            });
                            if (result.isConfirmed) {
                                window.location.reload();
                            }
                            return;
                        }

                        await swalSuccess({
                            icon: 'info',
                            title: 'El pago sigue en proceso',
                            text: 'Todavía está pendiente de confirmación. Te avisaremos por correo en cuanto se resuelva.',
                            confirmButtonText: 'Entendido',
                        });
                    } catch (e) {
                        console.error(e);
                        await swalSuccess({
                            icon: 'error',
                            title: 'No se pudo consultar el estado',
                            text: 'Inténtalo nuevamente en unos segundos.',
                            confirmButtonText: 'Entendido',
                        });
                    } finally {
                        this.checking = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>

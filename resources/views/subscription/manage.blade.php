<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Mi suscripción</h2>
    </x-slot>

    <div class="max-w-xl space-y-4">

        @if (session('success'))
            <div class="bg-green-50 border border-green-600 text-green-700 rounded-lg px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if ($subscription && $subscription->isActive())
            <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                <div class="h-1 bg-green-500"></div>
                <div class="p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-green-50 border-2 border-green-600 text-green-600 flex items-center justify-center shrink-0">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-carbon">Suscripción activa</p>
                            <p class="text-xs text-carbon/50">Plan {{ $subscription->subscriptionPlan?->name ?? $subscription->plan }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                        <div>
                            <p class="text-xs text-carbon/50 uppercase tracking-wide mb-0.5">Plan</p>
                            <p class="font-medium text-carbon">{{ $subscription->subscriptionPlan?->name ?? $subscription->plan }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-carbon/50 uppercase tracking-wide mb-0.5">Precio</p>
                            <p class="font-medium text-carbon">{{ $subscription->subscriptionPlan?->formattedPrice() ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-carbon/50 uppercase tracking-wide mb-0.5">Fecha de inicio</p>
                            <p class="font-medium text-carbon">{{ \Carbon\Carbon::parse($subscription->starts_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-carbon/50 uppercase tracking-wide mb-0.5">Fecha de vencimiento</p>
                            <p class="font-medium text-carbon">{{ \Carbon\Carbon::parse($subscription->ends_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-ink-100">
                        <form method="POST" action="{{ route('subscription.cancel') }}" x-data
                              @submit.prevent="
                                  const result = await swalConfirm({
                                      title: '¿Cancelar tu suscripción?',
                                      html: '<p style=&quot;margin-bottom:0.5rem&quot;>Perderás el acceso a la plataforma <strong>de inmediato</strong>, aunque te quede tiempo pagado.</p><p style=&quot;margin:0&quot;>Esta acción no se puede deshacer.</p>',
                                      icon: 'warning',
                                      iconColor: '#B54B3F',
                                      confirmButtonText: 'Sí, cancelar suscripción',
                                      cancelButtonText: 'No, mantener mi suscripción',
                                      confirmButtonColor: '#B54B3F',
                                  });
                                  if (result.isConfirmed) $el.submit();
                              ">
                            @csrf
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-rust/10 hover:bg-rust/20 text-rust text-sm font-medium px-5 py-2.5 rounded-md border border-rust/20 transition">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Cancelar suscripción
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        @elseif ($subscription)
            <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                <div class="h-1 bg-ink-200"></div>
                <div class="p-6 space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-full bg-ink-50 border-2 border-ink-200 text-ink-600 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-carbon">
                                @if ($subscription->status === 'cancelled')
                                    Suscripción cancelada
                                @else
                                    Suscripción vencida
                                @endif
                            </p>
                            <p class="text-xs text-carbon/50 mt-1 leading-relaxed">
                                @if ($subscription->status === 'cancelled')
                                    Tu suscripción fue cancelada el {{ \Carbon\Carbon::parse($subscription->ends_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}.
                                @else
                                    Tu suscripción venció el {{ \Carbon\Carbon::parse($subscription->ends_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <a href="{{ route('subscription.show') }}"
                           class="inline-flex items-center justify-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-5 py-2.5 rounded-md transition">
                            Elegir un plan nuevo
                        </a>
                    </div>
                </div>
            </div>

        @else
            <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                <div class="h-1 bg-brass-400"></div>
                <div class="p-8 space-y-4 text-center">
                    <div class="mx-auto h-12 w-12 rounded-full bg-brass-50 border-2 border-brass-400 text-brass-500 flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-carbon">Aún no tienes una suscripción</p>
                        <p class="text-xs text-carbon/50 mt-1">Elige un plan para comenzar a generar certificados.</p>
                    </div>
                    <div>
                        <a href="{{ route('subscription.show') }}"
                           class="inline-flex items-center justify-center gap-2 bg-brass-500 hover:bg-brass-600 text-white text-sm font-medium px-6 py-2.5 rounded-md transition">
                            Ver planes disponibles
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <a href="{{ route('dashboard') }}" class="inline-flex text-sm text-ink-700 hover:text-brass-600 transition">
            &larr; Volver al panel
        </a>
    </div>
</x-app-layout>

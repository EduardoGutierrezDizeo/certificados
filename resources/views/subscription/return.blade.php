<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Confirmando tu pago</h2>
    </x-slot>

    <div class="max-w-lg" x-data="paymentReturn('{{ $estadoInicial }}', '{{ $reference }}')" x-init="init()">
        <div class="bg-white border border-ink-100 rounded-lg p-8 text-center">

            <template x-if="state === 'checking'">
                <div>
                    <svg class="mx-auto h-10 w-10 animate-spin text-brass-500 mb-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <p class="text-sm text-carbon/70 mb-4">
                        Estamos confirmando tu pago con el banco. Esto puede tardar unos segundos...
                    </p>
                    <button @click="state = 'failed'" class="text-xs text-carbon/40 hover:text-carbon/60 underline">
                        ¿Cancelaste o algo salió mal? Reintentar el pago
                    </button>
                </div>
            </template>

            <template x-if="state === 'confirmed'">
                <div>
                    <div class="mx-auto h-14 w-14 rounded-full bg-green-50 border-2 border-green-600 text-green-600 flex items-center justify-center mb-4">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-carbon mb-4">¡Pago confirmado! Tu suscripción ya está activa.</p>
                    <a href="{{ route('dashboard') }}" class="inline-flex bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                        Ir al panel
                    </a>
                </div>
            </template>

            <template x-if="state === 'pendingConfirmation'">
                <div>
                    <div class="mx-auto h-14 w-14 rounded-full bg-brass-50 border-2 border-brass text-brass-500 flex items-center justify-center mb-4">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-carbon mb-2">Tu pago está en proceso de verificación de seguridad.</p>
                    <p class="text-xs text-carbon/50 mb-6">
                        Tu banco está verificando la transacción con tu tarjeta (3DS), lo que puede tardar unos minutos.
                        En cuanto se resuelva, recibirás un correo de confirmación y tu cuenta se activará automáticamente:
                        no necesitas pagar de nuevo.
                        Puedes cerrar esta página con tranquilidad.
                    </p>
                    <a href="{{ route('dashboard') }}" class="inline-flex bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                        Ir al panel
                    </a>
                </div>
            </template>

            <template x-if="state === 'failed'">
                <div>
                    <div class="mx-auto h-14 w-14 rounded-full bg-rust/10 border-2 border-rust text-rust flex items-center justify-center mb-4">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-carbon mb-2">El pago no se completó.</p>
                    <p class="text-xs text-carbon/50 mb-6">
                        Puede que hayas cancelado el proceso o que el banco no haya respondido a tiempo. No se te realizó ningún cobro.
                    </p>
                    <a href="{{ route('subscription.show') }}" class="inline-flex bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                        Intentar de nuevo
                    </a>
                </div>
            </template>

            <template x-if="state === 'timedOut'">
                <div>
                    <p class="text-sm font-medium text-carbon mb-2">El pago aún no se confirma.</p>
                    <p class="text-xs text-carbon/50 mb-6">
                        Si tu pago está en verificación de seguridad (3DS), puede tardar unos minutos más:
                        recibirás un correo de confirmación y tu cuenta se activará automáticamente, sin necesidad de pagar otra vez.
                        <a href="{{ route('dashboard') }}" class="text-ink-700 underline">Puedes entrar al panel</a> mientras tanto.
                        Solo intenta pagar de nuevo si estás seguro de que la transacción no se completó.
                    </p>
                    <a href="{{ route('subscription.show') }}" class="inline-flex bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                        Reintentar el pago
                    </a>
                </div>
            </template>
        </div>
    </div>

    <script>
        function paymentReturn(estadoInicial, reference) {
            return {
                state: estadoInicial,
                attempts: 0,
                timer: null,

                init() {
                    if (this.state !== 'checking') return;

                    this.timer = setInterval(async () => {
                        this.attempts++;
                        try {
                            const url = new URL('{{ route('subscription.status') }}');
                            if (reference) url.searchParams.set('reference', reference);
                            const data = await (await fetch(url)).json();
                            if (data.active) {
                                this.state = 'confirmed';
                                clearInterval(this.timer);
                                setTimeout(() => window.location.href = '{{ route('dashboard') }}', 1500);
                            }
                        } catch (e) {
                            console.error(e);
                        }

                        if (this.attempts > 20 && this.state === 'checking') {
                            this.state = 'timedOut';
                            clearInterval(this.timer);
                        }
                    }, 3000);
                },
            };
        }
    </script>
</x-app-layout>

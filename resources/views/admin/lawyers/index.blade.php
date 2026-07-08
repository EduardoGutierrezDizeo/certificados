<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-serif text-2xl text-ink-700">Abogados</h2>
            <a href="{{ route('admin.lawyers.create') }}"
               class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-4 py-2 rounded-md transition">
                + Nueva cuenta
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-4">

        @if (session('success'))
            <div class="bg-green-50 border border-green-600 text-green-700 text-sm rounded-lg px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @if (session('generated_password'))
            <div class="bg-brass-50 border border-brass-400 rounded-lg p-5">
                <p class="text-sm font-medium text-ink-700 mb-2">
                    Cuenta creada para {{ session('generated_email') }}
                </p>
                <p class="text-xs text-carbon/60 mb-1">Contraseña temporal (solo se muestra una vez):</p>
                <code class="block bg-white border border-brass-400 rounded px-3 py-2 text-sm font-mono text-ink-700">
                    {{ session('generated_password') }}
                </code>
                <p class="text-xs text-carbon/50 mt-2">Compártela con el abogado por un medio seguro. Se le pedirá cambiarla en su primer ingreso.</p>
            </div>
        @endif

        @if (request()->query('subscription') === 'active')
            <div class="flex items-center gap-3 text-sm text-carbon/70">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-brass-50 text-brass-600">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4" />
                    </svg>
                    Suscripciones activas
                </span>
                <span>Mostrando solo abogados con suscripción activa.</span>
                <a href="{{ route('admin.lawyers.index') }}" class="text-ink-700 underline underline-offset-2 hover:text-brass-600 transition">Ver todos</a>
            </div>
        @endif

        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface border-b border-ink-100">
                    <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                        <th class="px-5 py-3">Nombre</th>
                        <th class="px-5 py-3">Correo</th>
                        <th class="px-5 py-3">Suscripción</th>
                        <th class="px-5 py-3">Vence</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($lawyers as $lawyer)
                        @php $sub = $lawyer->subscriptions->first(); @endphp
                        <tr>
                            <td class="px-5 py-3.5 text-carbon">{{ $lawyer->name }}</td>
                            <td class="px-5 py-3.5 text-carbon/70">{{ $lawyer->email }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                    {{ match($sub?->status) {
                                        'active' => 'bg-green-50 text-green-700',
                                        'suspended' => 'bg-brass-50 text-brass-600',
                                        'cancelled' => 'bg-rust/10 text-rust',
                                        default => 'bg-ink-50 text-ink-600',
                                    } }}">
                                    {{ $sub ? ucfirst($sub->status) : 'Sin suscripción' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-carbon/60">
                                {{ $sub?->ends_at?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.lawyers.payments', $lawyer) }}"
                                   class="text-xs font-medium text-ink-700 hover:underline mr-3">
                                    Ver pagos
                                </a>
                                @if ($sub && $sub->status === 'active')
                                    <form method="POST" action="{{ route('admin.lawyers.subscription.suspend', $lawyer) }}" class="inline"
                                          id="suspend-form-{{ $lawyer->id }}">
                                        @csrf
                                        <button type="button" class="text-xs font-medium text-rust hover:underline"
                                                @click="$store.confirm.open(
                                                    'Suspender suscripción',
                                                    '¿Suspender la suscripción de {{ $lawyer->name }}?',
                                                    'Suspender',
                                                    'brass',
                                                    () => document.getElementById('suspend-form-{{ $lawyer->id }}').submit()
                                                )">
                                            Suspender
                                        </button>
                                    </form>
                                @elseif ($sub && $sub->status === 'suspended')
                                    <form method="POST" action="{{ route('admin.lawyers.subscription.reactivate', $lawyer) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-medium text-green-700 hover:underline">Reactivar</button>
                                    </form>
                                @endif

                                @if ($sub && in_array($sub->status, ['active', 'suspended']))
                                    <form method="POST" action="{{ route('admin.lawyers.subscription.cancel', $lawyer) }}" class="inline ml-3"
                                          id="cancel-form-{{ $lawyer->id }}">
                                        @csrf
                                        <button type="button" class="text-xs font-medium text-carbon/50 hover:underline"
                                                @click="$store.confirm.open(
                                                    'Cancelar suscripción',
                                                    '¿Cancelar definitivamente la suscripción de {{ $lawyer->name }}?',
                                                    'Cancelar',
                                                    'rust',
                                                    () => document.getElementById('cancel-form-{{ $lawyer->id }}').submit()
                                                )">
                                            Cancelar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-carbon/50">
                                Aún no hay abogados registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $lawyers->links() }}
    </div>

    <x-confirm-modal />
</x-app-layout>

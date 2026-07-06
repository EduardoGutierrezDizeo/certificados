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

        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface border-b border-ink-100">
                    <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                        <th class="px-5 py-3">Nombre</th>
                        <th class="px-5 py-3">Correo</th>
                        <th class="px-5 py-3">Suscripción</th>
                        <th class="px-5 py-3">Vence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($lawyers as $lawyer)
                        @php $sub = $lawyer->subscriptions->first(); @endphp
                        <tr>
                            <td class="px-5 py-3.5 text-carbon">{{ $lawyer->name }}</td>
                            <td class="px-5 py-3.5 text-carbon/70">{{ $lawyer->email }}</td>
                            <td class="px-5 py-3.5">
                                @if ($sub)
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ $sub->status === 'active' ? 'bg-green-50 text-green-700' : 'bg-ink-50 text-ink-600' }}">
                                        {{ ucfirst($sub->status) }}
                                    </span>
                                @else
                                    <span class="text-carbon/40 text-xs">Sin suscripción</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-carbon/60">
                                {{ $sub?->ends_at?->format('d/m/Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-carbon/50">
                                Aún no hay abogados registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $lawyers->links() }}
    </div>
</x-app-layout>

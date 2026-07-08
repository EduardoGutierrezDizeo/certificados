<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-serif text-2xl text-ink-700">Historial de pagos</h2>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-4">

        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface border-b border-ink-100">
                    <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                        <th class="px-5 py-3">Fecha</th>
                        <th class="px-5 py-3">Referencia</th>
                        <th class="px-5 py-3 text-right">Monto</th>
                        <th class="px-5 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-5 py-3.5 text-carbon/60 text-xs">
                                {{ $payment->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3.5 font-mono text-xs text-carbon/50">
                                {{ $payment->reference }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-mono text-carbon">
                                ${{ number_format($payment->amount_in_cents / 100, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                    {{ match($payment->status) {
                                        'approved' => 'bg-green-50 text-green-700',
                                        'declined' => 'bg-rust/10 text-rust',
                                        'error' => 'bg-rust/10 text-rust',
                                        'voided' => 'bg-ink-50 text-ink-600',
                                        default => 'bg-brass-50 text-brass-600',
                                    } }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-carbon/50">
                                Aún no tienes pagos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $payments->links() }}

        <a href="{{ route('dashboard') }}" class="inline-flex text-sm text-ink-700 hover:text-brass-600 transition">
            &larr; Volver al panel
        </a>
    </div>
</x-app-layout>

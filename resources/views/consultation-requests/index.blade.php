<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Historial de consultas</h2>
    </x-slot>

    <div class="max-w-5xl space-y-4">

        <form method="GET" class="bg-white border border-ink-100 rounded-lg p-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-carbon/60 mb-1">Buscar por documento</label>
                <input type="text" name="document_number" value="{{ request('document_number') }}"
                       placeholder="1004819300"
                       class="w-full rounded-md border-ink-100 text-sm font-mono focus:border-ink-600 focus:ring-ink-600">
            </div>

            <div class="min-w-[160px]">
                <label class="block text-xs font-medium text-carbon/60 mb-1">Estado</label>
                <select name="status" class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    <option value="">Todos</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pendiente</option>
                    <option value="success" @selected(request('status') === 'success')>Exitoso</option>
                    <option value="partial" @selected(request('status') === 'partial')>Parcial</option>
                    <option value="failed" @selected(request('status') === 'failed')>Fallido</option>
                </select>
            </div>

            <button type="submit" class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-4 py-2 rounded-md transition">
                Filtrar
            </button>

            @if (request('document_number') || request('status'))
                <a href="{{ route('consultation-requests.index') }}" class="text-sm text-carbon/50 hover:text-carbon px-2">
                    Limpiar
                </a>
            @endif
        </form>

        @if ($consultationRequests->isEmpty())
            <div class="bg-white border border-ink-100 rounded-lg p-10 text-center">
                <p class="text-sm text-carbon/50">No hay consultas que coincidan con este filtro.</p>
            </div>
        @else
            <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-surface border-b border-ink-100">
                        <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                            <th class="px-5 py-3">Consultado</th>
                            <th class="px-5 py-3">Documento</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3">Fecha</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($consultationRequests as $cr)
                            <tr>
                                <td class="px-5 py-3.5 text-carbon">
                                    {{ $cr->subject->full_name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 font-mono text-carbon/70">
                                    {{ $cr->subject->document_type }} {{ $cr->subject->document_number }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @php
                                        $badge = [
                                            'pending' => ['bg-ink-50 text-ink-600', 'Pendiente'],
                                            'success' => ['bg-green-50 text-green-700', 'Exitoso'],
                                            'partial' => ['bg-brass-50 text-brass-600', 'Parcial'],
                                            'failed' => ['bg-rust/10 text-rust', 'Fallido'],
                                        ][$cr->status] ?? ['bg-ink-50 text-ink-600', $cr->status];
                                    @endphp
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium {{ $badge[0] }}">
                                        {{ $badge[1] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-carbon/60">
                                    {{ $cr->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('consultation-requests.show', $cr) }}"
                                       class="text-ink-700 hover:text-brass-600 font-medium">
                                        Ver →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>
                {{ $consultationRequests->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
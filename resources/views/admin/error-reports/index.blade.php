<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-serif text-2xl text-ink-700">Reportes de error</h2>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-4">

        @if (session('success'))
            <div class="bg-green-50 border border-green-600 text-green-700 text-sm rounded-lg px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center gap-3 text-sm text-carbon/70">
            <a href="{{ route('admin.error-reports.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition
                      {{ ! request()->query('status') ? 'bg-ink-700 text-white' : 'bg-ink-50 text-ink-600 hover:bg-ink-100' }}">
                Todos
            </a>
            <a href="{{ route('admin.error-reports.index', ['status' => 'pending']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition
                      {{ request()->query('status') === 'pending' ? 'bg-brass-500 text-white' : 'bg-brass-50 text-brass-600 hover:bg-brass-100' }}">
                Pendientes
                @if ($pendingCount > 0)
                    <span class="bg-white/20 px-1.5 rounded-full">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.error-reports.index', ['status' => 'resolved']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium transition
                      {{ request()->query('status') === 'resolved' ? 'bg-green-600 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                Resueltos
            </a>
        </div>

        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface border-b border-ink-100">
                    <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                        <th class="px-5 py-3">Asunto</th>
                        <th class="px-5 py-3">Abogado</th>
                        <th class="px-5 py-3">Categoria</th>
                        <th class="px-5 py-3">Fecha</th>
                        <th class="px-5 py-3 text-center">Estado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($errorReports as $report)
                        <tr class="{{ $report->status === 'pending' ? 'bg-brass-50/30' : '' }}">
                            <td class="px-5 py-3.5 text-carbon font-medium">
                                {{ Str::limit($report->subject, 40) }}
                            </td>
                            <td class="px-5 py-3.5 text-carbon/70">
                                {{ $report->lawyer->name }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-ink-50 text-ink-600">
                                    {{ ucfirst($report->category) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-carbon/60 text-xs">
                                {{ $report->created_at->format('d/m/Y') }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                                    {{ match($report->status) {
                                        'pending' => 'bg-brass-50 text-brass-600',
                                        'resolved' => 'bg-green-50 text-green-700',
                                        default => 'bg-ink-50 text-ink-600',
                                    } }}">
                                    {{ $report->status === 'pending' ? 'Pendiente' : 'Resuelto' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.error-reports.show', $report) }}"
                                   class="text-xs font-medium text-ink-700 hover:underline">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-carbon/50">
                                No hay reportes de error.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $errorReports->links() }}
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Panel de administración</h2>
    </x-slot>

    <div class="max-w-5xl space-y-6">

        {{-- Metrics grid --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <a href="{{ route('admin.lawyers.index') }}"
               class="block bg-white border border-ink-100 rounded-lg p-5 hover:border-brass-400 transition-colors">
                <p class="text-xs text-carbon/50 uppercase tracking-wide">Abogados registrados</p>
                <p class="font-serif text-3xl text-ink-700 mt-1">{{ number_format($totalLawyers) }}</p>
            </a>
            <a href="{{ route('admin.lawyers.index', ['subscription' => 'active']) }}"
               class="block bg-white border border-ink-100 rounded-lg p-5 hover:border-brass-400 transition-colors">
                <p class="text-xs text-carbon/50 uppercase tracking-wide">Suscripciones activas</p>
                <p class="font-serif text-3xl text-ink-700 mt-1">{{ number_format($activeSubscriptions) }}</p>
            </a>
            <div class="bg-white border border-ink-100 rounded-lg p-5">
                <p class="text-xs text-carbon/50 uppercase tracking-wide">Consultas generadas</p>
                <p class="font-serif text-3xl text-ink-700 mt-1">{{ number_format($totalConsultations) }}</p>
            </div>
            <div class="bg-white border border-ink-100 rounded-lg p-5">
                <p class="text-xs text-carbon/50 uppercase tracking-wide">Certificados exitosos</p>
                <p class="font-serif text-3xl text-ink-700 mt-1">{{ number_format($successfulCertificates) }}</p>
            </div>
            <div class="bg-white border border-ink-100 rounded-lg p-5">
                <p class="text-xs text-carbon/50 uppercase tracking-wide">Recaudado este mes</p>
                <p class="font-serif text-3xl text-brass-600 mt-1">${{ number_format($monthlyRevenue, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white border border-ink-100 rounded-lg p-5">
                <p class="text-xs text-carbon/50 uppercase tracking-wide">Recaudado histórico</p>
                <p class="font-serif text-3xl text-brass-600 mt-1">${{ number_format($totalRevenue, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Bar chart: certificates by site --}}
        <div class="bg-white border border-ink-100 rounded-lg p-6">
            <h3 class="font-serif text-lg text-ink-700 mb-5">Certificados exitosos por sitio</h3>

            @php
                $sites = [
                    'rnmc' => 'Medidas Correctivas (RNMC)',
                    'judicial_police' => 'Antecedentes Judiciales',
                    'comptroller' => 'Antecedentes Fiscales',
                    'attorney_general' => 'Antecedentes Disciplinarios',
                ];
                $maxValue = max($bySite->values()->all() ?: [1]);
            @endphp

            <div class="space-y-4">
                @foreach ($sites as $key => $label)
                    @php $value = $bySite[$key] ?? 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-carbon">{{ $label }}</span>
                            <span class="text-carbon/50 font-mono">{{ number_format($value) }}</span>
                        </div>
                        <div class="w-full bg-ink-50 rounded-full h-3 overflow-hidden">
                            <div class="h-full rounded-full bg-brass transition-all duration-500"
                                 style="width: {{ $maxValue > 0 ? ($value / $maxValue) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lawyers ranking table --}}
        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <div class="px-5 py-4 border-b border-ink-100">
                <h3 class="font-serif text-lg text-ink-700">Consultas por abogado</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-surface border-b border-ink-100">
                    <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                        <th class="px-5 py-3">#</th>
                        <th class="px-5 py-3">Nombre</th>
                        <th class="px-5 py-3">Correo</th>
                        <th class="px-5 py-3 text-center">Consultas</th>
                        <th class="px-5 py-3 text-center">Certificados exitosos</th>
                        <th class="px-5 py-3">Última consulta</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($lawyers as $lawyer)
                        <tr>
                            <td class="px-5 py-3.5 text-carbon/40 font-mono text-xs">{{ $loop->iteration }}</td>
                            <td class="px-5 py-3.5 text-carbon">{{ $lawyer->name }}</td>
                            <td class="px-5 py-3.5 text-carbon/70">{{ $lawyer->email }}</td>
                            <td class="px-5 py-3.5 text-center font-mono text-carbon">{{ number_format($lawyer->consultation_requests_count) }}</td>
                            <td class="px-5 py-3.5 text-center font-mono text-carbon">{{ number_format((int) $lawyer->successful_certificates) }}</td>
                            <td class="px-5 py-3.5 text-carbon/60">
                                {{ $lawyer->last_consultation_at?->format('d/m/Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-carbon/50">
                                No hay abogados registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $lawyers->links() }}
    </div>
</x-app-layout>

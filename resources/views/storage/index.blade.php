<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Almacenamiento</h2>
    </x-slot>

    <div class="max-w-6xl space-y-4">

        @if (session('status'))
            <div class="bg-green-50 border border-green-600 text-green-700 text-sm rounded-lg px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        {{-- Contador de uso --}}
        <div class="bg-white border border-ink-100 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-carbon">Espacio utilizado</p>
                <p class="mt-1 text-2xl font-mono text-ink-700">
                    {{ number_format($used) }} <span class="text-lg text-carbon/40">/ {{ number_format($limit) }}</span>
                    <span class="text-sm font-sans text-carbon/50">PDFs</span>
                </p>
            </div>
            <div class="w-full sm:w-64">
                <div class="h-2.5 bg-ink-50 rounded-full overflow-hidden">
                    @php
                        $percent = $limit > 0 ? min(100, round(($used / $limit) * 100)) : 0;
                    @endphp
                    <div class="h-full rounded-full {{ $percent >= 90 ? 'bg-rust' : 'bg-brass-500' }}"
                         style="width: {{ $percent }}%"></div>
                </div>
                <p class="mt-1 text-xs text-carbon/40 text-right">{{ $percent }}%</p>
            </div>
        </div>

        {{-- Toggle de vista --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('storage.index', ['view' => 'grouped']) }}"
                class="px-4 py-2 text-sm font-medium rounded-md transition {{ $view === 'grouped' ? 'bg-ink-700 text-white' : 'bg-white text-carbon/70 border border-ink-100 hover:bg-surface' }}">
                Agrupar por consulta
            </a>
            <a href="{{ route('storage.index', ['view' => 'individual']) }}"
                class="px-4 py-2 text-sm font-medium rounded-md transition {{ $view === 'individual' ? 'bg-ink-700 text-white' : 'bg-white text-carbon/70 border border-ink-100 hover:bg-surface' }}">
                Individual
            </a>
        </div>

        @php
            $siteLabels = [
                'rnmc' => 'RNMC',
                'judicial_police' => 'Antecedentes Judiciales',
                'comptroller' => 'Contraloría',
                'attorney_general' => 'Procuraduría',
            ];
        @endphp

        {{-- Vista agrupada por consulta --}}
        @if ($view === 'grouped')
            @if ($consultations->isEmpty())
                <div class="bg-white border border-ink-100 rounded-lg p-10 text-center">
                    <p class="text-sm text-carbon/50">No hay PDFs guardados todavía.</p>
                </div>
            @else
                <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-surface border-b border-ink-100">
                            <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                                <th class="px-5 py-3">Fecha</th>
                                <th class="px-5 py-3">Persona</th>
                                <th class="px-5 py-3">Estado</th>
                                <th class="px-5 py-3">Tamaño</th>
                                <th class="px-5 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($consultations as $consultation)
                                <tr>
                                    <td class="px-5 py-3.5 text-carbon/60">
                                        {{ $consultation->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="text-carbon">{{ $consultation->subject->full_name ?? '—' }}</p>
                                        <p class="text-xs font-mono text-carbon/50">
                                            {{ $consultation->subject->document_type }} {{ $consultation->subject->document_number }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if ($consultation->is_complete)
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">Completo</span>
                                        @else
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-rust/10 text-rust">Incompleto</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-carbon/70">
                                        {{ $consultation->total_size > 0 ? $consultation->size_label : '—' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button type="button"
                                                @click="confirmFreeConsultation({{ $consultation->id }})"
                                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-rust border border-rust/30 rounded-md hover:bg-rust/5 transition">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $consultations->links() }}
                </div>
            @endif
        @endif

        {{-- Vista individual --}}
        @if ($view === 'individual')
            <div x-data="storageBulk()" class="space-y-4">
                <div class="flex items-center justify-end">
                    <button type="button"
                            @click="bulkDelete()"
                            :disabled="selected.length === 0"
                            :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-rust/90'"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-rust rounded-md transition disabled:opacity-40">
                        Eliminar seleccionados
                        <span x-show="selected.length > 0" class="ml-2 text-xs bg-white/20 px-1.5 py-0.5 rounded-full"
                              x-text="selected.length"></span>
                    </button>
                </div>

                @if ($certificates->isEmpty())
                    <div class="bg-white border border-ink-100 rounded-lg p-10 text-center">
                        <p class="text-sm text-carbon/50">No hay PDFs guardados en vista individual.</p>
                    </div>
                @else
                    <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-surface border-b border-ink-100">
                                <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                                    <th class="px-5 py-3 w-10">
                                        <input type="checkbox"
                                               @change="toggleAll($event.target.checked)"
                                               :checked="isAllSelected()"
                                               class="rounded border-ink-100 text-ink-700 focus:ring-ink-600">
                                    </th>
                                    <th class="px-5 py-3">Fecha</th>
                                    <th class="px-5 py-3">Persona</th>
                                    <th class="px-5 py-3">Sitio</th>
                                    <th class="px-5 py-3">Tamaño</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                @foreach ($certificates as $certificate)
                                    <tr>
                                        <td class="px-5 py-3.5">
                                            <input type="checkbox"
                                                   value="{{ $certificate->id }}"
                                                   @change="toggle($el)"
                                                   :checked="isSelected({{ $certificate->id }})"
                                                   class="rounded border-ink-100 text-ink-700 focus:ring-ink-600">
                                        </td>
                                        <td class="px-5 py-3.5 text-carbon/60">
                                            {{ $certificate->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <p class="text-carbon">{{ $certificate->consultationRequest->subject->full_name ?? '—' }}</p>
                                            <p class="text-xs font-mono text-carbon/50">
                                                {{ $certificate->consultationRequest->subject->document_type }} {{ $certificate->consultationRequest->subject->document_number }}
                                            </p>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-brass-50 text-brass-600">
                                                {{ $siteLabels[$certificate->site] ?? $certificate->site }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 font-mono text-carbon/70">
                                            {{ $certificate->size_bytes > 0 ? $certificate->size_label : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $certificates->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <script>
        function confirmFreeConsultation(consultationId) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            swalConfirm({
                title: 'Liberar almacenamiento',
                text: 'Se borrarán todos los PDFs de esta consulta de la unidad de almacenamiento. El registro se conservará y podrá regenerarse.',
                icon: 'warning',
                confirmButtonText: 'Sí, liberar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#B54B3F',
            }).then((result) => {
                if (!result.isConfirmed) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/storage/consultations/${consultationId}`;
                form.innerHTML = `
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <input type="hidden" name="_method" value="DELETE">
                `;
                document.body.appendChild(form);
                form.submit();
            });
        }

        @if ($view === 'individual')
        function storageBulk() {
            return {
                selected: [],
                ids: @json($certificates->pluck('id')->all()),

                toggle(el) {
                    const id = Number(el.value);
                    const index = this.selected.indexOf(id);
                    if (el.checked) {
                        if (index === -1) this.selected.push(id);
                    } else if (index !== -1) {
                        this.selected.splice(index, 1);
                    }
                },

                toggleAll(checked) {
                    if (checked) {
                        this.selected = [...this.ids];
                    } else {
                        this.selected = [];
                    }
                },

                isSelected(id) {
                    return this.selected.includes(id);
                },

                isAllSelected() {
                    return this.selected.length === this.ids.length && this.ids.length > 0;
                },

                bulkDelete() {
                    if (this.selected.length === 0) return;

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                    swalConfirm({
                        title: 'Liberar almacenamiento',
                        text: `Se liberarán ${this.selected.length} PDF seleccionado(s). Los registros se conservarán y podrán regenerarse.`,
                        icon: 'warning',
                        confirmButtonText: 'Sí, liberar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#B54B3F',
                    }).then((result) => {
                        if (!result.isConfirmed) return;

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/storage/certificates';
                        let inputs = `
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                        `;
                        this.selected.forEach((id) => {
                            inputs += `<input type="hidden" name="ids[]" value="${id}">`;
                        });
                        form.innerHTML = inputs;
                        document.body.appendChild(form);
                        form.submit();
                    });
                },
            };
        }
        @endif
    </script>
</x-app-layout>

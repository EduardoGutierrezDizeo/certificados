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
        <div x-data="historyPoller({{ $consultationRequests->pluck('id')->toJson() }})" class="bg-white border border-ink-100 rounded-lg overflow-hidden">
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
                            <tr x-data="{ status: '{{ $cr->status }}' }" data-cr-id="{{ $cr->id }}">
                                <td class="px-5 py-3.5 text-carbon">
                                    {{ $cr->subject->full_name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 font-mono text-carbon/70">
                                    {{ $cr->subject->document_type }} {{ $cr->subject->document_number }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span x-init="$nextTick(() => $el.classList.add(statusClass(status)))"
                                          :class="statusClass(status)"
                                          class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium"
                                          x-text="statusLabel(status)">
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-carbon/60">
                                    {{ $cr->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <template x-if="status === 'pending' || status === 'processing'">
                                            <button @click="cancelarGeneracion({{ $cr->id }})"
                                                    class="text-rust/50 hover:text-rust transition"
                                                    title="Cancelar generación">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </template>

                                        <template x-if="status !== 'pending' && status !== 'processing' && status !== 'cancelled'">
                                            <button @click="regenerarConsulta({{ $cr->id }})"
                                                    class="text-ink-700 hover:text-brass-600 transition"
                                                    title="Regenerar consulta">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </template>

                                        <a x-show="status === 'success' || status === 'partial'"
                                           href="{{ route('consultation-requests.download-zip', $cr) }}"
                                           class="text-green-700 hover:text-green-600 transition"
                                           title="Descargar comprimido">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                                            </svg>
                                        </a>

                                        <button @click="eliminarConsulta({{ $cr->id }})"
                                                class="text-rust/50 hover:text-rust transition"
                                                title="Eliminar consulta">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>

                                        <a href="{{ route('consultation-requests.show', $cr) }}"
                                           class="text-ink-700 hover:text-brass-600 transition"
                                           title="Ver detalle">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    </div>
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

    <script>
        function historyPoller(ids) {
            return {
                statuses: {},
                pollTimer: null,
                csrfToken: document.querySelector('meta[name="csrf-token"]').content,

                async init() {
                    await this.fetchStatuses();

                    const hasPending = Object.values(this.statuses).some(
                        s => s === 'pending' || s === 'processing'
                    );

                    if (hasPending) {
                        this.pollTimer = setInterval(async () => {
                            await this.fetchStatuses();

                            const stillPending = Object.values(this.statuses).some(
                                s => s === 'pending' || s === 'processing'
                            );
                            if (!stillPending) {
                                clearInterval(this.pollTimer);
                            }
                        }, 5000);
                    }
                },

                async fetchStatuses() {
                    try {
                        const params = ids.map(id => `ids[]=${id}`).join('&');
                        const res = await fetch(`/consultation-requests/status?${params}`);
                        const data = await res.json();
                        this.statuses = data;

                        Object.entries(data).forEach(([id, status]) => {
                            const row = this.$el.querySelector(`tr[data-cr-id="${id}"]`);
                            if (row && row._x_dataStack) {
                                row._x_dataStack[0].status = status;
                            }
                        });
                    } catch (e) {
                        console.error('Error polling status:', e);
                    }
                },

                async cancelarGeneracion(crId) {
                    const result = await swalConfirm({
                        title: 'Cancelar generación',
                        text: 'Se detendrá el procesamiento de esta consulta.',
                        icon: 'warning',
                        confirmButtonText: 'Sí, cancelar',
                        cancelButtonText: 'Volver',
                        confirmButtonColor: '#B54B3F',
                    });
                    if (!result.isConfirmed) return;

                    try {
                        const res = await fetch(`/consultation-requests/${crId}/cancel`, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrfToken },
                        });
                        if (res.ok) {
                            const row = this.$el.querySelector(`[x-data]`);
                            if (row && row._x_dataStack) {
                                row._x_dataStack[0].status = 'cancelled';
                            }
                        }
                    } catch (e) {
                        console.error('Error cancelando:', e);
                    }
                },

                async regenerarConsulta(crId) {
                    const result = await swalConfirm({
                        title: 'Regenerar consulta',
                        text: 'Se creará una nueva consulta con los mismos datos.',
                        icon: 'question',
                        confirmButtonText: 'Sí, regenerar',
                        cancelButtonText: 'Cancelar',
                    });
                    if (!result.isConfirmed) return;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/consultation-requests/${crId}/regenerate`;
                    form.innerHTML = `<input type="hidden" name="_token" value="${this.csrfToken}">`;
                    document.body.appendChild(form);
                    form.submit();
                },

                async eliminarConsulta(crId) {
                    const result = await swalConfirm({
                        title: 'Eliminar consulta',
                        text: 'Esta acción no se puede deshacer. Se eliminarán todos los certificados asociados.',
                        icon: 'warning',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#B54B3F',
                    });
                    if (!result.isConfirmed) return;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `/consultation-requests/${crId}`;
                    form.innerHTML = `
                        <input type="hidden" name="_token" value="${this.csrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                },

                statusClass(status) {
                    const map = {
                        pending: 'bg-ink-50 text-ink-600',
                        processing: 'bg-brass-50 text-brass-600',
                        success: 'bg-green-50 text-green-700',
                        partial: 'bg-brass-50 text-brass-600',
                        failed: 'bg-rust/10 text-rust',
                        cancelled: 'bg-carbon/5 text-carbon/40',
                    };
                    return map[status] || 'bg-ink-50 text-ink-600';
                },

                statusLabel(status) {
                    const map = {
                        pending: 'Pendiente',
                        processing: 'Procesando',
                        success: 'Exitoso',
                        partial: 'Parcial',
                        failed: 'Fallido',
                        cancelled: 'Cancelado',
                    };
                    return map[status] || status;
                },
            };
        }
    </script>
</x-app-layout>

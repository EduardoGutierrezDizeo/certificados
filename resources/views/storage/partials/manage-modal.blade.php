{{--
    Modal reutilizable de gestión de almacenamiento.

    Se abre escuchando el evento de ventana `open-manage-modal` con
    `{ detail: { needed: int } }`, donde `needed` es la cantidad de PDFs
    extra que hará falta liberar para continuar con la operación.

    El estado Alpine es independiente del componente que lo incluya:
    incluir este partial FUERA de cualquier `<form>` / `x-data` de la vista.

    Uso:
        @include('storage.partials.manage-modal')
--}}
<div
    x-data="manageStorageModal($el)"
    data-url="{{ route('storage.data') }}"
    x-cloak
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center"
    @open-manage-modal.window="show($event.detail ?? {})"
>
    <div
        class="fixed inset-0 bg-ink-900/50"
        @click="open = false"
    ></div>

    <div
        class="relative bg-white border border-ink-100 rounded-lg w-full max-w-4xl mx-4 shadow-xl"
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="open = false"
    >
        <div class="p-6 border-b border-ink-100">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-serif text-xl text-ink-700">Gestionar almacenamiento</h3>
                    <p class="text-sm text-carbon/60 mt-1">
                        Estás intentando guardar
                        <span x-text="needed" class="font-mono font-medium text-ink-700"></span>
                        PDF(s) más. Libera espacio del que prefieras para continuar, o cierra y cancela.
                    </p>
                </div>
                <button type="button" class="text-carbon/40 hover:text-carbon transition" @click="open = false" aria-label="Cerrar">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 flex items-center gap-4">
                <p class="text-sm text-carbon/70">
                    <span class="font-mono font-medium text-ink-700" x-text="used.toLocaleString('es-CO')"></span>
                    <span class="text-carbon/40"> / </span>
                    <span class="font-mono" x-text="limit.toLocaleString('es-CO')"></span>
                    PDFs usados
                </p>
                <div class="w-full sm:w-64">
                    <div class="h-2.5 bg-ink-50 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="limit > 0 && (used / limit) >= 0.9 ? 'bg-rust' : 'bg-brass-500'"
                            :style="`width: ${limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0}%`"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-md border border-ink-100 overflow-hidden">
                    <button type="button"
                        @click="setView('grouped')"
                        :class="view === 'grouped' ? 'bg-ink-700 text-white' : 'bg-white text-carbon/70 hover:bg-surface'"
                        class="px-4 py-2 text-sm font-medium transition">
                        Agrupar por consulta
                    </button>
                    <button type="button"
                        @click="setView('individual')"
                        :class="view === 'individual' ? 'bg-ink-700 text-white' : 'bg-white text-carbon/70 hover:bg-surface'"
                        class="px-4 py-2 text-sm font-medium transition">
                        Individual
                    </button>
                </div>

                <button type="button"
                    x-show="view === 'individual' && selectedIds.length > 0"
                    @click="deleteBulk()"
                    :disabled="deleting"
                    :class="deleting ? 'opacity-40 cursor-not-allowed' : 'hover:bg-rust/90'"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-rust rounded-md transition">
                    Eliminar seleccionados
                    <span class="ml-2 text-xs bg-white/20 px-1.5 py-0.5 rounded-full" x-text="selectedIds.length"></span>
                </button>
            </div>

            <template x-if="loading && items.length === 0">
                <div class="flex justify-center py-10">
                    <svg class="animate-spin h-6 w-6 text-brass-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
            </template>

            <template x-if="!loading && items.length === 0">
                <div class="bg-white border border-ink-100 rounded-lg p-10 text-center">
                    <p class="text-sm text-carbon/50">No hay PDFs guardados todavía.</p>
                </div>
            </template>

            <template x-if="items.length > 0 && view === 'grouped'">
                <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-surface border-b border-ink-100">
                            <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                                <th class="px-5 py-3">
                                    <button type="button" @click="toggleDir()" class="inline-flex items-center gap-1 hover:text-ink-700 transition" title="Alternar orden">
                                        Fecha
                                        <span class="text-brass-600" x-text="dir === 'asc' ? '↑' : '↓'"></span>
                                    </button>
                                </th>
                                <th class="px-5 py-3">Persona</th>
                                <th class="px-5 py-3">Estado</th>
                                <th class="px-5 py-3">Tamaño</th>
                                <th class="px-5 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <template x-for="row in items" :key="row.id">
                                <tr>
                                    <td class="px-5 py-3.5 text-carbon/60" x-text="formatDate(row.created_at)"></td>
                                    <td class="px-5 py-3.5">
                                        <p class="text-carbon" x-text="row.subject?.full_name ?? '—'"></p>
                                        <p class="text-xs font-mono text-carbon/50"
                                            x-text="row.subject ? `${row.subject.document_type} ${row.subject.document_number}` : ''"></p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span x-show="row.is_complete"
                                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">Completo</span>
                                        <span x-show="!row.is_complete"
                                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-rust/10 text-rust">Incompleto</span>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-carbon/70">
                                        <span x-text="row.total_size > 0 ? row.size_label : '—'"></span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button type="button"
                                            @click="deleteConsultation(row.id)"
                                            :disabled="deleting"
                                            :class="deleting ? 'opacity-40 cursor-not-allowed' : 'hover:bg-rust/5'"
                                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-rust border border-rust/30 rounded-md transition">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <template x-if="items.length > 0 && view === 'individual'">
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
                                <th class="px-5 py-3">
                                    <button type="button" @click="toggleDir()" class="inline-flex items-center gap-1 hover:text-ink-700 transition" title="Alternar orden">
                                        Fecha
                                        <span class="text-brass-600" x-text="dir === 'asc' ? '↑' : '↓'"></span>
                                    </button>
                                </th>
                                <th class="px-5 py-3">Persona</th>
                                <th class="px-5 py-3">Sitio</th>
                                <th class="px-5 py-3">Tamaño</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <template x-for="row in items" :key="row.id">
                                <tr>
                                    <td class="px-5 py-3.5">
                                        <input type="checkbox"
                                            @change="toggleSelected(row.id)"
                                            :checked="isSelected(row.id)"
                                            class="rounded border-ink-100 text-ink-700 focus:ring-ink-600">
                                    </td>
                                    <td class="px-5 py-3.5 text-carbon/60" x-text="formatDate(row.created_at)"></td>
                                    <td class="px-5 py-3.5">
                                        <p class="text-carbon" x-text="row.consultation_request?.subject?.full_name ?? '—'"></p>
                                        <p class="text-xs font-mono text-carbon/50"
                                            x-text="row.consultation_request?.subject ? `${row.consultation_request.subject.document_type} ${row.consultation_request.subject.document_number}` : ''"></p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-brass-50 text-brass-600"
                                            x-text="siteLabel(row.site)"></span>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-carbon/70">
                                        <span x-text="row.size_bytes > 0 ? row.size_label : '—'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <div x-show="!loading && items.length > 0" class="flex items-center justify-between pt-1">
                <button type="button"
                    @click="prevPageUrl && fetchData(prevPageUrl)"
                    :disabled="!prevPageUrl || loading || deleting"
                    :class="(!prevPageUrl || loading || deleting) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-surface'"
                    class="text-sm font-medium text-carbon/70 border border-ink-100 rounded-md px-4 py-2 transition">
                    ← Anterior
                </button>
                <button type="button"
                    @click="nextPageUrl && fetchData(nextPageUrl)"
                    :disabled="!nextPageUrl || loading || deleting"
                    :class="(!nextPageUrl || loading || deleting) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-surface'"
                    class="text-sm font-medium text-carbon/70 border border-ink-100 rounded-md px-4 py-2 transition">
                    Siguiente →
                </button>
            </div>
        </div>

        <div class="p-6 border-t border-ink-100 flex items-center justify-end gap-3">
            <button type="button"
                class="text-sm font-medium text-carbon/60 hover:text-carbon px-4 py-2 rounded-md transition"
                @click="open = false">
                Cancelar
            </button>
        </div>
    </div>
</div>
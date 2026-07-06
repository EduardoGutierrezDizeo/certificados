<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">
            {{ $consultationRequest->subject->full_name ?? 'Consulta #' . $consultationRequest->id }}
        </h2>
        <p class="text-sm text-carbon/60 mt-1 font-mono">
            {{ $consultationRequest->subject->document_type }} {{ $consultationRequest->subject->document_number }}
        </p>
    </x-slot>

    <div x-data="consultationProgress({{ $consultationRequest->id }}, {{ $consultationRequest->certificateRequests->toJson() }})"
        x-init="init()" class="max-w-3xl space-y-4">
        <template x-if="!allDone">
            <div class="flex items-center gap-2 text-sm text-carbon/60">
                <svg class="animate-spin h-4 w-4 text-brass-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Procesando, puedes esperar aquí o volver más tarde — tu progreso se guarda.
            </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <template x-for="cert in certificates" :key="cert.id">
                <div class="bg-white border border-ink-100 rounded-lg p-5 flex items-start gap-4">

                    <div class="shrink-0 h-12 w-12 rounded-full border-2 flex items-center justify-center" :class="{
                            'border-ink-100 text-ink-100': cert.status === 'pending',
                            'border-brass-400 text-brass-500': cert.status === 'processing',
                            'border-green-600 bg-green-50 text-green-600': cert.status === 'success',
                            'border-rust bg-rust/5 text-rust': cert.status === 'failed',
                         }">
                        <svg x-show="cert.status === 'pending'" class="h-5 w-5" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                        </svg>
                        <svg x-show="cert.status === 'processing'" class="h-5 w-5 animate-spin" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg x-show="cert.status === 'success'" class="h-6 w-6" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M5 13l4 4L19 7" />
                        </svg>
                        <svg x-show="cert.status === 'failed'" class="h-6 w-6" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-carbon" x-text="siteLabel(cert.site)"></p>

                        <p x-show="cert.status === 'pending'" class="text-xs text-carbon/50 mt-0.5">En cola...</p>
                        <p x-show="cert.status === 'processing'" class="text-xs text-brass-600 mt-0.5">Procesando...</p>
                        <p x-show="cert.status === 'failed'" class="text-xs text-rust mt-0.5"
                            x-text="cert.error_message"></p>
                        <button x-show="cert.status === 'failed'" @click="retry(cert.id)"
                            class="inline-flex items-center gap-1 text-xs font-medium text-ink-700 hover:text-brass-600 mt-1">
                            Reintentar
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>

                        <a x-show="cert.status === 'success'" :href="cert.download_url"
                            class="inline-flex items-center gap-1 text-xs font-medium text-ink-700 hover:text-brass-600 mt-1">
                            Descargar PDF
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                            </svg>
                        </a>
                    </div>
                </div>
            </template>
        </div>

        <div class="pt-2">
            <a href="{{ route('dashboard') }}" class="text-sm text-ink-700 hover:text-brass-600 font-medium">
                ← Generar otra consulta
            </a>
        </div>
    </div>

    <script>
        function consultationProgress(id, initialCertificates) {
            return {
                certificates: initialCertificates,
                pollTimer: null,

                get allDone() {
                    return this.certificates.every(c => c.status === 'success' || c.status === 'failed');
                },

                siteLabel(site) {
                    const labels = {
                        rnmc: 'Medidas Correctivas (RNMC)',
                        judicial_police: 'Antecedentes Judiciales',
                        comptroller: 'Antecedentes Fiscales',
                        attorney_general: 'Antecedentes Disciplinarios',
                    };
                    return labels[site] ?? site;
                },

                init() {
                    if (this.allDone) return;

                    this.pollTimer = setInterval(async () => {
                        try {
                            const res = await fetch(`/consultation-requests/${id}/status`);
                            const data = await res.json();
                            this.certificates = data.certificates;

                            if (this.allDone) {
                                clearInterval(this.pollTimer);
                            }
                        } catch (e) {
                            console.error('Error consultando estado:', e);
                        }
                    }, 3000);
                },

                async retry(certificateId) {
                    const cert = this.certificates.find(c => c.id === certificateId);
                    cert.status = 'processing';

                    try {
                        await fetch(`/certificate-requests/${certificateId}/retry`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                        });

                        if (!this.pollTimer) {
                            this.init();
                        }
                    } catch (e) {
                        console.error('Error reintentando:', e);
                        cert.status = 'failed';
                    }
                },
            };
        }
    </script>
</x-app-layout>
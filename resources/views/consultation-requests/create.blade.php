<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Nueva consulta</h2>
        <p class="text-sm text-carbon/60 mt-1">Selecciona los certificados que necesitas generar.</p>
    </x-slot>

    <div class="max-w-3xl">
        <form method="POST" action="{{ route('consultation-requests.store') }}" x-data="{ documentType: '' }">
            @csrf

            <div class="bg-white border border-ink-100 rounded-lg p-6 space-y-6">
                <div>
                    <h3 class="font-serif text-lg text-ink-700 mb-4">Datos del consultado</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="document_type" class="block text-sm font-medium text-carbon mb-1">Tipo de documento</label>
                            <select id="document_type" name="document_type" x-model="documentType"
                                    class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                                <option value="">Seleccione...</option>
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="CE">Cédula de Extranjería</option>
                                <option value="PA">Pasaporte</option>
                                <option value="NIT">NIT</option>
                            </select>
                            @error('document_type')
                                <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="document_number" class="block text-sm font-medium text-carbon mb-1">Número de documento</label>
                            <input type="text" id="document_number" name="document_number" value="{{ old('document_number') }}"
                                   class="w-full rounded-md border-ink-100 text-sm font-mono focus:border-ink-600 focus:ring-ink-600">
                            @error('document_number')
                                <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="documentType === 'CC'" x-cloak>
                            <label for="issuance_date" class="block text-sm font-medium text-carbon mb-1">
                                Fecha de expedición
                            </label>
                            <input type="date" id="issuance_date" name="issuance_date" value="{{ old('issuance_date') }}"
                                   class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                            <p class="mt-1 text-xs text-carbon/50">Requerida para RNMC y Procuraduría.</p>
                            @error('issuance_date')
                                <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="full_name" class="block text-sm font-medium text-carbon mb-1">
                                Nombre completo <span class="text-carbon/40 font-normal">(opcional)</span>
                            </label>
                            <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}"
                                   class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                            <p class="mt-1 text-xs text-carbon/50">Ayuda a resolver preguntas de verificación de Procuraduría.</p>
                        </div>

                        <div>
                            <label for="company_name" class="block text-sm font-medium text-carbon mb-1">
                                Razón social <span class="text-carbon/40 font-normal">(opcional)</span>
                            </label>
                            <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}"
                                   class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                        </div>
                    </div>
                </div>

                <div class="border-t border-ink-100 pt-6">
                    <h3 class="font-serif text-lg text-ink-700 mb-1">Certificados a generar</h3>
                    <p class="text-sm text-carbon/60 mb-4">Elige uno o varios.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ([
                            'rnmc' => ['Medidas Correctivas', 'RNMC — Policía Nacional'],
                            'judicial_police' => ['Antecedentes Judiciales', 'Policía Nacional'],
                            'comptroller' => ['Antecedentes Fiscales', 'Contraloría General'],
                            'attorney_general' => ['Antecedentes Disciplinarios', 'Procuraduría General'],
                        ] as $value => [$title, $subtitle])
                            <label class="flex items-start gap-3 border border-ink-100 rounded-md p-4 cursor-pointer has-[:checked]:border-brass-500 has-[:checked]:bg-brass-50 transition">
                                <input type="checkbox" name="sites[]" value="{{ $value }}"
                                       {{ in_array($value, old('sites', [])) ? 'checked' : '' }}
                                       class="mt-0.5 rounded border-ink-100 text-brass-500 focus:ring-brass-500">
                                <span>
                                    <span class="block text-sm font-medium text-carbon">{{ $title }}</span>
                                    <span class="block text-xs text-carbon/50">{{ $subtitle }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('sites')
                        <p class="mt-2 text-xs text-rust">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                    Generar certificados
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
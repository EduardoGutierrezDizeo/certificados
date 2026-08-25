<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Reportar problema</h2>
        <p class="text-sm text-carbon/60 mt-1">Cuéntanos qué falló y lo revisaremos pronto.</p>
    </x-slot>

    <div class="max-w-3xl">

        @if (session('success'))
            <div class="bg-green-50 border border-green-600 text-green-700 text-sm rounded-lg px-4 py-3 mb-6">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('error-reports.store') }}">
            @csrf

            <div class="bg-white border border-ink-100 rounded-lg p-6 space-y-6">

                <div>
                    <label for="subject" class="block text-sm font-medium text-carbon mb-1">Asunto</label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    @error('subject')
                        <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-carbon mb-1">Categoría</label>
                    <select id="category" name="category"
                            class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                        <option value="">Seleccione...</option>
                        <option value="pago" {{ old('category') === 'pago' ? 'selected' : '' }}>Pago</option>
                        <option value="certificado" {{ old('category') === 'certificado' ? 'selected' : '' }}>Certificado</option>
                        <option value="otro" {{ old('category') === 'otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('category')
                        <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-carbon mb-1">Descripción</label>
                    <textarea id="description" name="description" rows="6"
                              class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                    Enviar reporte
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

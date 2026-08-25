<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Crear plan de suscripción</h2>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ route('admin.subscription-plans.store') }}" class="bg-white border border-ink-100 rounded-lg p-6 space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-carbon mb-1">Nombre del plan</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Ej: Estándar"
                       class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                @error('name') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="price_in_cents" class="block text-sm font-medium text-carbon mb-1">Precio (COP)</label>
                    <input type="number" id="price_in_cents" name="price_in_cents" value="{{ old('price_in_cents') }}" min="0" placeholder="50000"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    <p class="mt-1 text-xs text-carbon/50">El precio se guarda en centavos (×100).</p>
                    @error('price_in_cents') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="duration_months" class="block text-sm font-medium text-carbon mb-1">Duración (meses)</label>
                    <input type="number" id="duration_months" name="duration_months" value="{{ old('duration_months', 1) }}" min="1"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    @error('duration_months') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-carbon mb-1">
                    Descripción <span class="text-carbon/40 font-normal">(opcional)</span>
                </label>
                <textarea id="description" name="description" rows="3"
                          class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600 resize-none"
                          placeholder="Describe los beneficios de este plan...">{{ old('description') }}</textarea>
                @error('description') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div class="pt-2 flex justify-end gap-3">
                <a href="{{ route('admin.subscription-plans.index') }}"
                   class="text-sm text-carbon/60 hover:text-carbon transition py-2 px-4">
                    Cancelar
                </a>
                <button type="submit" class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-2.5 rounded-md transition">
                    Crear plan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

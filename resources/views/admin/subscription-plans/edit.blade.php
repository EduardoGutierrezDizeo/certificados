<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Editar plan: {{ $plan->name }}</h2>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}" x-data="{ wasActive: {{ Js::from($plan->is_active) }} }" @submit.prevent="
            const checkbox = $el.querySelector('input[name=&quot;is_active&quot;]');
            const isDeactivating = wasActive && !checkbox.checked;
            if (isDeactivating) {
                const result = await swalConfirm({
                    title: '¿Desactivar este plan?',
                    text: 'Los usuarios no podrán seleccionar este plan para nuevas suscripciones.',
                    icon: 'warning',
                    iconColor: '#B08D57',
                    confirmButtonText: 'Sí, desactivar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#16324F',
                });
                if (!result.isConfirmed) return;
            }
            $el.submit();
        " class="bg-white border border-ink-100 rounded-lg p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-medium text-carbon mb-1">Nombre del plan</label>
                <input type="text" id="name" name="name" value="{{ old('name', $plan->name) }}"
                       class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                @error('name') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="price_in_cents" class="block text-sm font-medium text-carbon mb-1">Precio (COP)</label>
                    <input type="number" id="price_in_cents" name="price_in_cents"
                           value="{{ old('price_in_cents', $plan->price_in_cents / 100) }}" min="0"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    <p class="mt-1 text-xs text-carbon/50">El precio se guarda en centavos (×100).</p>
                    @error('price_in_cents') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="duration_months" class="block text-sm font-medium text-carbon mb-1">Duración (meses)</label>
                    <input type="number" id="duration_months" name="duration_months"
                           value="{{ old('duration_months', $plan->duration_months) }}" min="1"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    @error('duration_months') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-carbon mb-1">
                    Descripción <span class="text-carbon/40 font-normal">(opcional)</span>
                </label>
                <textarea id="description" name="description" rows="3"
                          class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600 resize-none">{{ old('description', $plan->description) }}</textarea>
                @error('description') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 py-2">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           class="sr-only peer"
                           {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
                    <div class="w-9 h-5 bg-ink-200 peer-focus:ring-2 peer-focus:ring-ink-400 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
                </label>
                <span class="text-sm text-carbon font-medium">Plan activo</span>
            </div>
            @error('is_active') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror

            <div class="pt-2 flex justify-end gap-3">
                <a href="{{ route('admin.subscription-plans.index') }}"
                   class="text-sm text-carbon/60 hover:text-carbon transition py-2 px-4">
                    Cancelar
                </a>
                <button type="submit" class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-2.5 rounded-md transition">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

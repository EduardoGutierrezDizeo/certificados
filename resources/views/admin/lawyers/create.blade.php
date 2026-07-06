<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Nueva cuenta de abogado</h2>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ route('admin.lawyers.store') }}" class="bg-white border border-ink-100 rounded-lg p-6 space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-carbon mb-1">Nombre completo</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                @error('name') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-carbon mb-1">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                @error('email') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="plan" class="block text-sm font-medium text-carbon mb-1">Plan</label>
                    <input type="text" id="plan" name="plan" value="{{ old('plan', 'standard') }}"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    @error('plan') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="duration_months" class="block text-sm font-medium text-carbon mb-1">Vigencia (meses)</label>
                    <input type="number" id="duration_months" name="duration_months" value="{{ old('duration_months', 1) }}" min="1" max="36"
                           class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                    @error('duration_months') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
                    Crear cuenta
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

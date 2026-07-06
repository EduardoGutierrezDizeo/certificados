<x-guest-layout>
    <div class="mb-6">
        <h2 class="font-serif text-xl text-ink-700">Actualiza tu contraseña</h2>
        <p class="text-sm text-carbon/60 mt-1">
            Por seguridad, debes establecer una nueva contraseña antes de continuar.
        </p>
    </div>

    <form method="POST" action="{{ route('password.force.update') }}">
        @csrf
        @method('PUT')

        <div>
            <label for="password" class="block text-sm font-medium text-carbon mb-1">Nueva contraseña</label>
            <input type="password" id="password" name="password" required
                   class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
            @error('password')
                <p class="mt-1 text-xs text-rust">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-4">
            <label for="password_confirmation" class="block text-sm font-medium text-carbon mb-1">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required
                   class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
        </div>

        <button type="submit"
                class="mt-6 w-full bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
            Guardar y continuar
        </button>
    </form>
</x-guest-layout>
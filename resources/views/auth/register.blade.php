<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" value="Nombre" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Terms Acceptance -->
        <div class="mt-4">
            <label for="terms_accepted" class="flex items-start gap-2">
                <input id="terms_accepted" name="terms_accepted" type="checkbox" value="1"
                       class="mt-0.5 rounded border-ink-200 text-ink-700 shadow-sm focus:ring-ink-500"
                       {{ old('terms_accepted') ? 'checked' : '' }}>
                <span class="text-sm text-carbon/60">
                    He leído y acepto los
                    <a href="{{ route('legal.terms') }}" target="_blank" class="underline hover:text-carbon">Términos y Condiciones</a>
                    y la
                    <a href="{{ route('legal.privacy') }}" target="_blank" class="underline hover:text-carbon">Política de Tratamiento de Datos Personales</a>.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms_accepted')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                ¿Ya tienes cuenta? Inicia sesión
            </a>

            <x-primary-button class="ms-4">
                Registrarme
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

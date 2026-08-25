<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        ¿Olvidaste tu contraseña? No hay problema. Déjanos saber tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Enviar enlace de restablecimiento
            </x-primary-button>
        </div>
    </form>

    <p class="text-center text-sm text-carbon/50 mt-6">
        <a href="{{ route('login') }}" class="text-ink-700 font-medium underline underline-offset-2 hover:text-brass-600 transition">
            &larr; Volver al inicio de sesión
        </a>
    </p>
</x-guest-layout>

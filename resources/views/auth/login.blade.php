<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="session('conflict_email', old('email'))" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">Recordarme</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif

            <x-primary-button class="ms-3">
                Iniciar sesión
            </x-primary-button>
        </div>
    </form>

    <p class="text-center text-sm text-carbon/50 mt-6">
        ¿No tienes cuenta?
        <a href="{{ route('register') }}" class="text-ink-700 font-medium underline underline-offset-2 hover:text-brass-600 transition">Regístrate</a>
    </p>

    {{-- Session Conflict Modal --}}
    @if (session('sessionConflict'))
        <form id="force-login-form" method="POST" action="{{ route('login.force') }}" class="hidden">
            @csrf
            <input type="hidden" name="force_token" value="{{ session('force_token') }}">
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    title: 'Ya tienes una sesión activa',
                    html: '<p style="color:#1F2429;font-size:0.875rem;line-height:1.6;">Tu cuenta está siendo usada en otro dispositivo o navegador. Si continúas, la sesión anterior se cerrará automáticamente.</p>',
                    icon: 'warning',
                    iconColor: '#B08D57',
                    showCancelButton: true,
                    confirmButtonText: 'Cerrar sesión activa y continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#B54B3F',
                    cancelButtonColor: '#F7F8FA',
                    cancelButtonStyling: false,
                    reverseButtons: true,
                    customClass: {
                        confirmButton: 'swal2-confirm-cc',
                        cancelButton: 'swal2-cancel-cc',
                        popup: 'swal2-popup-cc',
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('force-login-form').submit();
                    } else {
                        window.location.href = '{{ route("login") }}';
                    }
                });
            });
        </script>
        <style>
            .swal2-popup-cc {
                font-family: 'Inter', sans-serif;
                border-radius: 0.5rem;
                border: 1px solid #D5E1EB;
            }
            .swal2-popup-cc .swal2-title {
                font-family: 'Source Serif 4', serif;
                color: #16324F;
                font-weight: 600;
            }
            .swal2-popup-cc .swal2-html-container {
                margin-top: 0.5rem;
            }
            .swal2-confirm-cc {
                background-color: #B54B3F !important;
                color: #fff !important;
                font-weight: 500 !important;
                font-size: 0.875rem !important;
                border-radius: 0.375rem !important;
                padding: 0.5rem 1rem !important;
                box-shadow: none !important;
                transition: background-color 0.15s ease !important;
            }
            .swal2-confirm-cc:hover {
                background-color: #9c3f34 !important;
            }
            .swal2-cancel-cc {
                background-color: #F7F8FA !important;
                color: #1F2429 !important;
                font-weight: 500 !important;
                font-size: 0.875rem !important;
                border: 1px solid #D5E1EB !important;
                border-radius: 0.375rem !important;
                padding: 0.5rem 1rem !important;
                box-shadow: none !important;
                transition: background-color 0.15s ease !important;
            }
            .swal2-cancel-cc:hover {
                background-color: #E5E7EB !important;
            }
            .swal2-actions {
                gap: 0.75rem !important;
            }
        </style>
    @endif
</x-guest-layout>

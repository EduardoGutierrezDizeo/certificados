<x-guest-layout>
    <div class="mb-6">
        <h2 class="font-serif text-xl text-ink-700">
            {{ $isFirstTime ? 'Bienvenido a CertiCheck' : 'Contenido legal actualizado' }}
        </h2>
        <p class="text-sm text-carbon/60 mt-1">
            @if ($isFirstTime)
                Para comenzar a usar la plataforma, debes revisar y aceptar nuestros documentos legales.
            @else
                Nuestros términos y condiciones han sido actualizados. Por favor, revisa los cambios y acepta la nueva versión para continuar.
            @endif
        </p>
    </div>

    <div class="space-y-3 mb-6">
        <a href="{{ route('legal.terms') }}" target="_blank"
           class="block text-sm text-ink-700 underline hover:text-brass-600 transition">
            Términos y Condiciones de Uso →
        </a>
        <a href="{{ route('legal.privacy') }}" target="_blank"
           class="block text-sm text-ink-700 underline hover:text-brass-600 transition">
            Política de Tratamiento de Datos Personales →
        </a>
    </div>

    <form method="POST" action="{{ route('legal.accept.store') }}">
        @csrf

        <button type="submit"
                class="w-full bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition">
            Acepto y deseo continuar
        </button>
    </form>
</x-guest-layout>

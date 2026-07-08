<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'CertiCheck') }} — Certificados de antecedentes para abogados</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=source-serif-4:400,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-carbon antialiased bg-white">

        <!-- Nav -->
        <header class="border-b border-ink-100">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <x-application-logo class="h-8 w-8 text-ink-700" />
                    <span class="font-serif text-lg text-ink-700 tracking-tight">CertiCheck</span>
                </div>
                <div class="flex items-center gap-5">
                    <a href="{{ route('register') }}"
                       class="text-sm text-carbon/50 hover:text-ink-700 transition">
                        Crear cuenta
                    </a>
                    <a href="{{ route('login') }}"
                       class="text-sm font-medium text-ink-700 hover:text-brass-600 transition">
                        Iniciar sesión →
                    </a>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 pt-20 pb-24 grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="inline-block text-xs font-medium tracking-wide uppercase text-brass-600 bg-brass-50 px-3 py-1 rounded-full mb-6">
                    Para abogados en Colombia
                </span>
                <h1 class="font-serif text-4xl sm:text-5xl text-ink-700 leading-tight tracking-tight mb-6">
                    Cuatro certificados de antecedentes, en un solo clic.
                </h1>
                <p class="text-base text-carbon/70 leading-relaxed mb-8 max-w-md">
                    Deja de entrar sitio por sitio a llenar el mismo formulario cuatro veces.
                    CertiCheck consulta Contraloría, Policía, RNMC y Procuraduría por ti — y
                    te avisa en cuanto estén listos.
                </p>
                <div class="flex flex-col items-start gap-3">
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-7 py-3.5 rounded-md transition">
                        Iniciar sesión
                    </a>
                    <a href="{{ route('register') }}"
                       class="text-sm text-carbon/50 hover:text-ink-700 underline underline-offset-2 transition">
                        ¿Aún no tienes cuenta? Regístrate aquí
                    </a>
                </div>
            </div>

            <div class="relative flex items-center justify-center">
                <svg viewBox="0 0 320 320" class="w-full max-w-sm text-ink-700">
                    <g stroke="currentColor" stroke-width="1" stroke-linecap="round" opacity="0.25">
                        <line x1="160" y1="10" x2="160" y2="30" />
                        <line x1="160" y1="290" x2="160" y2="310" />
                        <line x1="10" y1="160" x2="30" y2="160" />
                        <line x1="290" y1="160" x2="310" y2="160" />
                        <line x1="52" y1="52" x2="66" y2="66" />
                        <line x1="254" y1="254" x2="268" y2="268" />
                        <line x1="52" y1="268" x2="66" y2="254" />
                        <line x1="254" y1="66" x2="268" y2="52" />
                    </g>
                    <circle cx="160" cy="160" r="110" stroke="currentColor" stroke-width="2" fill="none" opacity="0.9" />
                    <circle cx="160" cy="160" r="88" stroke="currentColor" stroke-width="1" fill="none" opacity="0.35" />
                    <path d="M116 162l28 28 60-62" stroke="#B08D57" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                </svg>
            </div>
        </section>

        <!-- Los 4 certificados -->
        <section class="bg-surface border-y border-ink-100 py-20">
            <div class="max-w-6xl mx-auto px-6">
                <h2 class="font-serif text-2xl text-ink-700 mb-2 text-center">Todo lo que necesitas, de una vez</h2>
                <p class="text-sm text-carbon/60 text-center mb-12">Selecciona los que necesites, el resto lo hacemos nosotros.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @foreach ([
                        ['Medidas Correctivas', 'RNMC — Policía Nacional'],
                        ['Antecedentes Judiciales', 'Policía Nacional'],
                        ['Antecedentes Fiscales', 'Contraloría General'],
                        ['Antecedentes Disciplinarios', 'Procuraduría General'],
                    ] as [$titulo, $entidad])
                        <div class="bg-white border border-ink-100 rounded-lg p-5">
                            <div class="h-9 w-9 rounded-full border-2 border-brass-400 flex items-center justify-center mb-4">
                                <svg class="h-4 w-4 text-brass-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-carbon">{{ $titulo }}</p>
                            <p class="text-xs text-carbon/50 mt-1">{{ $entidad }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Valor -->
        <section class="max-w-6xl mx-auto px-6 py-20 grid sm:grid-cols-3 gap-10">
            <div>
                <p class="font-serif text-3xl text-brass-600 mb-2">4×</p>
                <p class="text-sm font-medium text-carbon mb-1">Menos formularios</p>
                <p class="text-xs text-carbon/60 leading-relaxed">Un solo dato de entrada genera los cuatro certificados a la vez.</p>
            </div>
            <div>
                <p class="font-serif text-3xl text-brass-600 mb-2">En vivo</p>
                <p class="text-sm font-medium text-carbon mb-1">Seguimiento en tiempo real</p>
                <p class="text-xs text-carbon/60 leading-relaxed">Ve el estado de cada certificado mientras se procesa, sin recargar la página.</p>
            </div>
            <div>
                <p class="font-serif text-3xl text-brass-600 mb-2">Historial</p>
                <p class="text-sm font-medium text-carbon mb-1">Todo queda guardado</p>
                <p class="text-xs text-carbon/60 leading-relaxed">Consulta y descarga certificados anteriores cuando los necesites.</p>
            </div>
        </section>

        <!-- CTA final -->
        <section class="border-t border-ink-100 py-14">
            <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-6">
                <div>
                    <p class="font-serif text-xl text-ink-700">¿Ya tienes una cuenta activa?</p>
                    <p class="text-sm text-carbon/50 mt-1">
                        ¿No tienes cuenta?
                        <a href="{{ route('register') }}" class="text-ink-700 underline underline-offset-2 hover:text-brass-600 transition">Regístrate aquí</a>
                    </p>
                </div>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-7 py-3 rounded-md transition">
                    Iniciar sesión
                </a>
            </div>
        </section>

        <footer class="border-t border-ink-100 py-6">
            <div class="max-w-6xl mx-auto px-6 text-xs text-carbon/40">
                CertiCheck — Certificados de antecedentes para abogados en Colombia.
            </div>
        </footer>

    </body>
</html>
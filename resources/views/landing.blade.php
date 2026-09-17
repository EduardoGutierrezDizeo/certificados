<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'CertiCheck') }} — Certificados de antecedentes para abogados</title>
        <meta name="description" content="Descarga los antecedentes fiscales, disciplinarios, judiciales y las medidas correctivas en una sola consulta. Sin repetir el mismo formulario cuatro veces.">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon-dark.svg') }}" media="(prefers-color-scheme: dark)">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=source-serif-4:400,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @media (prefers-reduced-motion: no-preference) {
                .flow-line {
                    stroke-dasharray: 240;
                    stroke-dashoffset: 240;
                    animation: draw 1.1s ease-out forwards;
                }
                .flow-line.delay-1 { animation-delay: .15s; }
                .flow-line.delay-2 { animation-delay: .30s; }
                .flow-line.delay-3 { animation-delay: .45s; }
                .flow-line.delay-4 { animation-delay: .60s; }
                .flow-node {
                    opacity: 0;
                    transform: translateX(6px);
                    animation: appear .5s ease-out forwards;
                }
                .flow-node.n1 { animation-delay: .35s; }
                .flow-node.n2 { animation-delay: .50s; }
                .flow-node.n3 { animation-delay: .65s; }
                .flow-node.n4 { animation-delay: .80s; }
                .flow-zip { opacity: 0; animation: appear .5s ease-out forwards; animation-delay: 1s; }
            }
            @keyframes draw { to { stroke-dashoffset: 0; } }
            @keyframes appear { to { opacity: 1; transform: translateX(0); } }

            /* Scroll reveal: se activa al entrar en pantalla y se revierte al salir,
               tanto bajando como subiendo. */
            [data-reveal] {
                opacity: 0;
                transform: translateY(20px);
                transition: opacity .6s ease, transform .6s ease;
                will-change: opacity, transform;
            }
            [data-reveal].is-visible {
                opacity: 1;
                transform: translateY(0);
            }
            [data-reveal-side="left"] { transform: translateX(-20px); }
            [data-reveal-side="left"].is-visible { transform: translateX(0); }
            [data-reveal-side="right"] { transform: translateX(20px); }
            [data-reveal-side="right"].is-visible { transform: translateX(0); }

            @media (prefers-reduced-motion: reduce) {
                [data-reveal] { transition: none !important; opacity: 1 !important; transform: none !important; }
            }
        </style>
    </head>
    <body class="font-sans text-carbon antialiased bg-white">

        <!-- Nav -->
        <header class="border-b border-white/10 bg-ink-700">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <x-application-logo class="h-12 w-12 text-ink-100" />
                    <span class="font-serif text-2xl text-ink-100 tracking-tight">CertiCheck</span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('login') }}"
                       class="text-sm font-medium text-ink-100 hover:text-brass-400 transition">
                        Iniciar sesión
                    </a>
                    <a href="{{ route('register') }}"
                       class="text-sm font-medium text-ink-700 bg-brass-400 hover:bg-brass-300 hover:-translate-y-0.5 px-4 py-1.5 rounded-md transition">
                        Crear cuenta
                    </a>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 pt-20 pb-24 grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <h1 data-reveal style="transition-delay:0ms" class="font-serif text-4xl sm:text-5xl text-ink-700 leading-tight tracking-tight mb-6">
                    Una sola consulta
                </h1>
                <h1 data-reveal style="transition-delay:90ms" class="font-serif text-4xl sm:text-5xl text-ink-700 leading-tight tracking-tight mb-6">
                    Cuatro certificados
                </h1>
                <p data-reveal style="transition-delay:180ms" class="text-base text-carbon/70 leading-relaxed mb-4 max-w-md">
                    Contraloría, Policía, RNMC y Procuraduría piden los mismos datos por
                    separado. CertiCheck los consulta las cuatro a la vez y te entrega
                    los certificados en un comprimido, listos para anexar a la propuesta.
                </p>
                <p data-reveal style="transition-delay:260ms" class="text-sm text-carbon/50 leading-relaxed mb-8 max-w-md">
                    Ya no dependes de tener las cuatro pestañas abiertas ni de recordar
                    cuál certificado falta antes del cierre de un proceso.
                </p>
                <div data-reveal style="transition-delay:340ms" class="flex flex-col items-start gap-3">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center gap-2 bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-7 py-3.5 rounded-md transition">
                        Crea una cuenta ahora mismo
                    </a>
                    <a href="{{ route('login') }}"
                       class="text-sm text-carbon/50 hover:text-ink-700 underline underline-offset-2 transition">
                        Ya tengo cuenta, iniciar sesión
                    </a>
                </div>
            </div>

            <div class="relative flex items-center justify-center">
                <svg viewBox="0 0 560 380" class="w-full max-w-lg" role="img" aria-label="Diagrama: una consulta de cédula genera cuatro certificados que se entregan en un archivo comprimido">
                    <!-- input node -->
                    <rect x="20" y="163" width="132" height="54" rx="8" fill="white" stroke="#1F2937" stroke-opacity="0.15" />
                    <text x="86" y="184" text-anchor="middle" class="font-sans" font-size="10" fill="#6B6B6B">Consulta</text>
                    <text x="86" y="202" text-anchor="middle" font-family="'IBM Plex Mono', monospace" font-size="12.5" fill="#1F2937" font-weight="500">C.C. 1.023.456.789</text>

                    <!-- connecting lines: input -> 4 entities -->
                    <path class="flow-line delay-1" d="M152,180 C 200,180 200,45 250,45" fill="none" stroke="#B08D57" stroke-width="1.5" />
                    <path class="flow-line delay-2" d="M152,188 C 210,188 210,135 250,135" fill="none" stroke="#B08D57" stroke-width="1.5" />
                    <path class="flow-line delay-3" d="M152,192 C 210,192 210,245 250,245" fill="none" stroke="#B08D57" stroke-width="1.5" />
                    <path class="flow-line delay-4" d="M152,200 C 200,200 200,335 250,335" fill="none" stroke="#B08D57" stroke-width="1.5" />

                    <!-- 4 entity nodes -->
                    @foreach ([
                        ['y' => 20, 'label' => 'Contraloría', 'sub' => 'Antecedentes fiscales', 'n' => 'n1'],
                        ['y' => 110, 'label' => 'Policía Nacional', 'sub' => 'Antecedentes judiciales', 'n' => 'n2'],
                        ['y' => 220, 'label' => 'RNMC', 'sub' => 'Medidas correctivas', 'n' => 'n3'],
                        ['y' => 310, 'label' => 'Procuraduría', 'sub' => 'Antecedentes disciplinarios', 'n' => 'n4'],
                    ] as $node)
                        <g class="flow-node {{ $node['n'] }}">
                            <rect x="250" y="{{ $node['y'] }}" width="180" height="50" rx="7" fill="white" stroke="#1F2937" stroke-opacity="0.15" />
                            <circle cx="270" cy="{{ $node['y'] + 25 }}" r="6" fill="none" stroke="#B08D57" stroke-width="1.6" />
                            <path d="M267,{{ $node['y'] + 25 }} l2,3 l5,-6" stroke="#B08D57" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                            <text x="286" y="{{ $node['y'] + 21 }}" font-size="11.5" fill="#1F2937" font-weight="500">{{ $node['label'] }}</text>
                            <text x="286" y="{{ $node['y'] + 35 }}" font-size="9.5" fill="#6B6B6B">{{ $node['sub'] }}</text>
                        </g>
                    @endforeach

                    <!-- lines: entities -> zip -->
                    <path class="flow-line delay-1" d="M430,45 C 470,45 470,190 490,190" fill="none" stroke="#B08D57" stroke-width="1.5" opacity="0.6" />
                    <path class="flow-line delay-2" d="M430,135 C 465,135 465,190 490,190" fill="none" stroke="#B08D57" stroke-width="1.5" opacity="0.6" />
                    <path class="flow-line delay-3" d="M430,245 C 465,245 465,190 490,190" fill="none" stroke="#B08D57" stroke-width="1.5" opacity="0.6" />
                    <path class="flow-line delay-4" d="M430,335 C 470,335 470,190 490,190" fill="none" stroke="#B08D57" stroke-width="1.5" opacity="0.6" />

                    <!-- zip output -->
                    <g class="flow-zip">
                        <rect x="488" y="160" width="60" height="60" rx="8" fill="#1F2937" />
                        <path d="M510,172 v8 m0,6 v8 m0,6 v6" stroke="#B08D57" stroke-width="2.5" stroke-linecap="round" />
                        <text x="500" y="240" text-anchor="middle" font-family="'IBM Plex Mono', monospace" font-size="10" fill="#1F2937">1023456789.zip</text>
                    </g>
                </svg>
            </div>
        </section>

        <!-- Antes / Después -->
        <section class="bg-surface border-y border-ink-100 py-20">
            <div class="max-w-6xl mx-auto px-6">
                <h2 data-reveal class="font-serif text-2xl text-ink-700 mb-2 text-center">El mismo trámite, dividido en cuatro veces</h2>
                <p data-reveal style="transition-delay:80ms" class="text-sm text-carbon/60 text-center mb-12 max-w-lg mx-auto">
                    Cada entidad tiene su propio portal, su propio formulario y su propio tiempo
                    de respuesta. Así es como cambia el proceso con CertiCheck.
                </p>

                <div class="max-w-3xl mx-auto border border-ink-100 rounded-lg overflow-hidden bg-white">
                    <!-- header row -->
                    <div data-reveal class="grid grid-cols-2 divide-x divide-ink-100 border-b border-ink-100">
                        <p class="px-6 py-3 text-xs font-medium text-carbon/40">Consulta manual</p>
                        <p class="px-6 py-3 text-xs font-medium text-brass-700 bg-brass-50/40">Con CertiCheck</p>
                    </div>

                    <!-- comparison rows -->
                    @foreach ([
                        [
                            'antes' => 'Ingresar la cédula y los datos del contratista en 4 sitios distintos',
                            'despues' => 'Un formulario con los datos del contratista',
                        ],
                        [
                            'antes' => 'Revisar por separado si cada certificado ya quedó listo',
                            'despues' => 'Seguimiento del estado de las cuatro consultas en una pantalla',
                        ],
                        [
                            'antes' => 'Descargar y nombrar cada PDF a mano antes de anexarlo',
                            'despues' => 'Un comprimido con los cuatro PDF, listo para anexar',
                        ],
                        [
                            'antes' => 'Repetir todo desde cero si necesitas el mismo certificado después',
                            'despues' => 'Vuelves a generarlos buscando solo por cédula',
                        ],
                    ] as $fila)
                        <div data-reveal style="transition-delay:{{ $loop->index * 90 }}ms" class="grid grid-cols-2 divide-x divide-ink-100 border-b border-ink-100 last:border-b-0">
                            <div class="px-6 py-4 flex gap-3 text-sm text-carbon/70">
                                <span class="text-carbon/30 mt-0.5 shrink-0">—</span>
                                <span>{{ $fila['antes'] }}</span>
                            </div>
                            <div class="px-6 py-4 flex gap-3 text-sm text-carbon bg-brass-50/40">
                                <span class="text-brass-600 mt-0.5 shrink-0">✓</span>
                                <span>{{ $fila['despues'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Cómo funciona -->
        <section class="max-w-6xl mx-auto px-6 py-20">
            <h2 data-reveal class="font-serif text-2xl text-ink-700 mb-12 text-center">Cómo funciona</h2>
            <div class="grid sm:grid-cols-3 gap-10 max-w-4xl mx-auto">
                <div data-reveal style="transition-delay:0ms">
                    <div class="h-9 w-9 rounded-full border-2 border-brass-400 flex items-center justify-center mb-4 text-sm font-medium text-brass-600">1</div>
                    <p class="text-sm font-medium text-carbon mb-1">Ingresa los datos</p>
                    <p class="text-xs text-carbon/60 leading-relaxed">Un solo formulario con los datos de la persona, una sola vez.</p>
                </div>
                <div data-reveal style="transition-delay:120ms">
                    <div class="h-9 w-9 rounded-full border-2 border-brass-400 flex items-center justify-center mb-4 text-sm font-medium text-brass-600">2</div>
                    <p class="text-sm font-medium text-carbon mb-1">CertiCheck consulta las 4 entidades</p>
                    <p class="text-xs text-carbon/60 leading-relaxed">Sigue el avance de cada certificado en tiempo real, sin recargar la página.</p>
                </div>
                <div data-reveal style="transition-delay:240ms">
                    <div class="h-9 w-9 rounded-full border-2 border-brass-400 flex items-center justify-center mb-4 text-sm font-medium text-brass-600">3</div>
                    <p class="text-sm font-medium text-carbon mb-1">Descarga el comprimido</p>
                    <p class="text-xs text-carbon/60 leading-relaxed">Los 4 PDF en un solo archivo, listos para anexar.</p>
                </div>
            </div>
        </section>

        <!-- Los 4 certificados -->
        <section class="bg-surface border-y border-ink-100 py-20">
            <div class="max-w-6xl mx-auto px-6">
                <h2 data-reveal class="font-serif text-2xl text-ink-700 mb-2 text-center">Los cuatro certificados que exige cada proceso</h2>
                <p data-reveal style="transition-delay:80ms" class="text-sm text-carbon/60 text-center mb-12">Selecciona los que necesites; el resto lo hacemos nosotros.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @foreach ([
                        ['Medidas Correctivas', 'RNMC — Policía Nacional'],
                        ['Antecedentes Judiciales', 'Policía Nacional'],
                        ['Antecedentes Fiscales', 'Contraloría General'],
                        ['Antecedentes Disciplinarios', 'Procuraduría General'],
                    ] as [$titulo, $entidad])
                        <div data-reveal style="transition-delay:{{ $loop->index * 90 }}ms" class="bg-white border border-ink-100 rounded-lg p-5">
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

        <!-- Historial -->
        <section class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-2 gap-16 items-center">
            <div data-reveal data-reveal-side="left">
                <h2 class="font-serif text-2xl text-ink-700 mb-4">La segunda vez es un solo clic</h2>
                <p class="text-sm text-carbon/70 leading-relaxed mb-4 max-w-md">
                    Cuando ya consultaste a una persona antes, no vuelves a llenar el
                    formulario. Buscas por cédula en el historial y generas los certificados
                    de nuevo, actualizados a la fecha.
                </p>
            </div>
            <div data-reveal data-reveal-side="right" class="border border-ink-100 rounded-lg overflow-hidden bg-white">
                <div class="px-5 py-3 border-b border-ink-100">
                    <span class="text-xs font-medium text-carbon/50">Historial de consultas</span>
                </div>
                <div class="px-5 py-3 border-b border-ink-100 flex items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-carbon/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                    </svg>
                    <span style="font-family:'IBM Plex Mono', monospace" class="text-xs text-carbon/40">1.023.456.789</span>
                </div>
                @foreach ([
                    ['cc' => '1.023.456.789', 'fecha' => '12 sep 2026'],
                    ['cc' => '79.845.221', 'fecha' => '08 sep 2026'],
                    ['cc' => '52.331.904', 'fecha' => '02 sep 2026'],
                ] as $row)
                    <div class="px-5 py-3.5 border-b border-ink-100 last:border-b-0 flex items-center justify-between">
                        <div>
                            <p style="font-family:'IBM Plex Mono', monospace" class="text-xs text-carbon">{{ $row['cc'] }}</p>
                            <p class="text-[11px] text-carbon/40 mt-0.5">Última consulta: {{ $row['fecha'] }}</p>
                        </div>
                        <span class="text-xs font-medium text-brass-600 hover:text-brass-700 cursor-default">Generar de nuevo</span>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Contáctanos -->
        <section class="bg-surface border-y border-ink-100 py-20">
            <div class="max-w-6xl mx-auto px-6">
                <h2 data-reveal class="font-serif text-2xl text-ink-700 mb-2 text-center">Contáctanos</h2>
                <p data-reveal style="transition-delay:80ms" class="text-sm text-carbon/60 text-center mb-12 max-w-lg mx-auto">
                    ¿Tienes dudas sobre CertiCheck o necesitas ayuda con una consulta?
                    Escríbenos y te respondemos lo antes posible.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 max-w-2xl mx-auto">
                    <a data-reveal href="tel:+573135997282"
                       class="group bg-white border border-ink-100 rounded-lg p-5 flex items-center gap-4 hover:border-brass-400 hover:-translate-y-0.5 transition">
                        <div class="h-9 w-9 rounded-full border-2 border-brass-400 flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4 text-brass-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5.5A2.5 2.5 0 015.5 3h1.6a1 1 0 01.96.73l.9 3.1a1 1 0 01-.27 1l-1.2 1.2a12.4 12.4 0 005.48 5.48l1.2-1.2a1 1 0 011-.27l3.1.9a1 1 0 01.73.96v1.6A2.5 2.5 0 0118.5 21h-.2C9.4 20.8 3.2 14.6 3 5.7V5.5z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-carbon/50">Teléfono</p>
                            <p style="font-family:'IBM Plex Mono', monospace" class="text-sm text-carbon group-hover:text-brass-600 transition">+57 3135997282</p>
                        </div>
                    </a>

                    <a data-reveal style="transition-delay:120ms" href="mailto:certicheck@certicheck.site"
                       class="group bg-white border border-ink-100 rounded-lg p-5 flex items-center gap-4 hover:border-brass-400 hover:-translate-y-0.5 transition">
                        <div class="h-9 w-9 rounded-full border-2 border-brass-400 flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4 text-brass-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.5 7l8.5 6 8.5-6" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-carbon/50">Correo electrónico</p>
                            <p class="text-sm text-carbon break-all group-hover:text-brass-600 transition">certicheck@certicheck.site</p>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <footer class="border-t border-ink-100 py-6">
            <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-carbon/40">
                <span>CertiCheck — Certificados de antecedentes para abogados en Colombia.</span>
                <div class="flex gap-4">
                    <a href="{{ route('legal.terms') }}" class="hover:text-ink-700 transition">Términos y Condiciones</a>
                    <a href="{{ route('legal.privacy') }}" class="hover:text-ink-700 transition">Política de Datos</a>
                </div>
            </div>
        </footer>

        <script>
            (function () {
                var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                var items = document.querySelectorAll('[data-reveal]');

                if (prefersReduced || !('IntersectionObserver' in window)) {
                    items.forEach(function (el) { el.classList.add('is-visible'); });
                    return;
                }

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        // Se activa una sola vez, al bajar y cruzar el umbral.
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.15,
                    rootMargin: '0px 0px -10% 0px'
                });

                items.forEach(function (el) { observer.observe(el); });
            })();
        </script>

    </body>
</html>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Legal' }} — {{ config('app.name', 'CertiCheck') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=source-serif-4:400,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-carbon antialiased bg-white">

        <header class="border-b border-ink-100">
            <div class="max-w-3xl mx-auto px-6 h-16 flex items-center justify-between">
                <a href="{{ route('landing') }}" class="flex items-center gap-2.5">
                    <x-application-logo class="h-7 w-7 text-ink-700" />
                    <span class="font-serif text-lg text-ink-700 tracking-tight">CertiCheck</span>
                </a>
                <a href="{{ route('landing') }}" class="text-sm text-carbon/50 hover:text-ink-700 transition">
                    Volver al inicio
                </a>
            </div>
        </header>

        <main class="max-w-3xl mx-auto px-6 py-12">
            {{ $slot }}
        </main>

        <footer class="border-t border-ink-100 py-6">
            <div class="max-w-3xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-carbon/40">
                <span>CertiCheck — Certificados de antecedentes para abogados en Colombia.</span>
                <div class="flex gap-4">
                    <a href="{{ route('legal.terms') }}" class="hover:text-ink-700 transition">Términos y Condiciones</a>
                    <a href="{{ route('legal.privacy') }}" class="hover:text-ink-700 transition">Política de Datos</a>
                </div>
            </div>
        </footer>
    </body>
</html>

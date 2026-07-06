<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CertiCheck') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=source-serif-4:400,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-carbon antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center bg-surface px-4 py-10">

            <div class="flex items-center gap-3 mb-8">
                <x-application-logo class="h-10 w-10 text-ink-700" />
                <span class="font-serif text-2xl tracking-tight text-ink-700">CertiCheck</span>
            </div>

            <div class="w-full sm:max-w-md bg-white border border-ink-100 rounded-lg shadow-sm overflow-hidden">
                <div class="h-1 bg-brass"></div>
                <div class="px-8 py-8">
                    {{ $slot }}
                </div>
            </div>

            <p class="mt-8 text-xs text-carbon/50 tracking-wide">
                Certificados de antecedentes, verificados y en un solo lugar.
            </p>
        </div>
    </body>
</html>
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
    <body class="font-sans text-carbon antialiased bg-surface" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen flex">

            @include('layouts.navigation')

            <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
                 class="fixed inset-0 bg-ink-900/50 z-30 lg:hidden"></div>

            <div class="flex-1 flex flex-col min-w-0">
                <div class="lg:hidden flex items-center justify-between bg-ink-700 px-4 py-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <x-application-logo class="h-7 w-7 text-white" />
                        <span class="font-serif text-lg text-white">CertiCheck</span>
                    </a>
                    <button @click="sidebarOpen = true" class="text-white p-2">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                @isset($header)
                    <header class="bg-white border-b border-ink-100">
                        <div class="px-6 py-5">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
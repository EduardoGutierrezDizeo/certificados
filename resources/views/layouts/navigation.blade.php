<aside x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-ink-700 flex flex-col transition-transform duration-200 ease-in-out">
    <div class="h-16 flex items-center gap-3 px-6 border-b border-white/10">
        <x-application-logo class="h-8 w-8 text-brass-400" />
        <span class="font-serif text-xl text-white tracking-tight">CertiCheck</span>
    </div>

    <nav class="flex-1 px-3 py-6 space-y-1">
        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition
                  {{ request()->routeIs('dashboard') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Panel principal
        </a>

        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition
          {{ request()->routeIs('dashboard') || request()->routeIs('consultation-requests.create') || request()->routeIs('consultation-requests.store') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4v16m8-8H4" />
            </svg>
            Nueva consulta
        </a>

        <a href="{{ route('consultation-requests.index') }}"
            class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition
          {{ request()->routeIs('consultation-requests.index') ? 'bg-white/10 text-white' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            Historial
        </a>
    </nav>

    <div class="border-t border-white/10 p-3">
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-white/5 transition text-left">
                <div
                    class="h-8 w-8 rounded-full bg-brass-500/20 text-brass-400 flex items-center justify-center text-sm font-semibold shrink-0">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-white truncate">{{ Auth::user()->name }}</p>
                    <p class="text-xs text-white/50 truncate">{{ Auth::user()->email }}</p>
                </div>
                <svg class="h-4 w-4 text-white/50 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                </svg>
            </button>

            <div x-show="open" x-cloak @click.outside="open = false"
                class="absolute bottom-full mb-2 left-0 right-0 bg-white rounded-md shadow-lg border border-ink-100 overflow-hidden">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-carbon hover:bg-surface">
                    Mi perfil
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-rust hover:bg-surface">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>
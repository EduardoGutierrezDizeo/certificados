<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-serif text-2xl text-ink-700">Detalle del reporte</h2>
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium
                {{ match($errorReport->status) {
                    'pending' => 'bg-brass-50 text-brass-600',
                    'resolved' => 'bg-green-50 text-green-700',
                    default => 'bg-ink-50 text-ink-600',
                } }}">
                {{ $errorReport->status === 'pending' ? 'Pendiente' : 'Resuelto' }}
            </span>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-6">

        @if (session('success'))
            <div class="bg-green-50 border border-green-600 text-green-700 text-sm rounded-lg px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="bg-brass-50 border border-brass-500 text-brass-600 text-sm rounded-lg px-4 py-3">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-600 text-red-700 text-sm rounded-lg px-4 py-3">
                {{ session('error') }}
            </div>
        @endif

        {{-- Report details --}}
        <div class="bg-white border border-ink-100 rounded-lg p-6">
            <div class="pb-4 border-b border-ink-100">
                <p class="text-xs text-carbon/50 uppercase tracking-wide mb-1.5">Asunto</p>
                <p class="text-carbon font-medium text-lg">{{ $errorReport->subject }}</p>
            </div>

            <div class="grid grid-cols-2 gap-6 py-4 border-b border-ink-100">
                <div>
                    <p class="text-xs text-carbon/50 uppercase tracking-wide mb-1.5">Abogado</p>
                    <p class="text-carbon font-medium">{{ $errorReport->lawyer->name }}</p>
                    <p class="text-xs text-carbon/50 mt-0.5">{{ $errorReport->lawyer->email }}</p>
                </div>
                <div>
                    <p class="text-xs text-carbon/50 uppercase tracking-wide mb-1.5">Categoria</p>
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-ink-50 text-ink-600">
                        {{ ucfirst($errorReport->category) }}
                    </span>
                </div>
            </div>

            <div class="py-4 border-b border-ink-100">
                <p class="text-xs text-carbon/50 uppercase tracking-wide mb-1.5">Descripcion</p>
                <p class="text-carbon whitespace-pre-wrap">{{ $errorReport->description }}</p>
            </div>

            <div class="pt-4">
                <p class="text-xs text-carbon/50 uppercase tracking-wide mb-1.5">Fecha de envio</p>
                <p class="text-carbon text-sm">{{ $errorReport->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        {{-- Resolution info (if resolved) --}}
        @if ($errorReport->status === 'resolved')
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 space-y-3">
                <p class="text-xs text-green-600 uppercase tracking-wide font-medium">Resolucion</p>
                @if ($errorReport->admin_comment)
                    <p class="text-green-800 whitespace-pre-wrap">{{ $errorReport->admin_comment }}</p>
                @endif
                <div class="flex items-center gap-4 text-xs text-green-600/80">
                    <span>Resuelto por: {{ $errorReport->resolvedBy?->name ?? '---' }}</span>
                    <span>&middot;</span>
                    <span>{{ $errorReport->resolved_at?->format('d/m/Y H:i') ?? '---' }}</span>
                </div>
            </div>
        @endif

        {{-- Resend notification button (resolved only) --}}
        @if ($errorReport->status === 'resolved')
            <form method="POST" action="{{ route('admin.error-reports.resend-notification', $errorReport) }}">
                @csrf
                <button type="submit"
                        class="w-full bg-white border border-ink-100 rounded-lg px-6 py-3 text-sm font-medium text-ink-700 hover:bg-surface transition text-left flex items-center gap-3">
                    <svg class="h-5 w-5 text-ink-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Reenviar notificación por correo
                </button>
            </form>
        @endif

        {{-- Resolve form (if pending) --}}
        @if ($errorReport->status === 'pending')
            <form x-data
                  method="POST" action="{{ route('admin.error-reports.resolve', $errorReport) }}"
                  @submit.prevent="
                      const result = await swalConfirm({
                          title: '¿Marcar este reporte como resuelto?',
                          text: 'El abogado recibirá un correo notificándole. Esta acción no se puede deshacer.',
                          icon: 'warning',
                          iconColor: '#B08D57',
                          confirmButtonText: 'Sí, marcar como resuelto',
                          cancelButtonText: 'Cancelar',
                          confirmButtonColor: '#16324F',
                      });
                      if (result.isConfirmed) $el.submit();
                  "
                  class="bg-white border border-ink-100 rounded-lg p-6 space-y-4">
                @csrf
                @method('PATCH')

                <h3 class="font-serif text-lg text-ink-700">Marcar como resuelto</h3>

                <div>
                    <label for="admin_comment" class="block text-sm font-medium text-carbon mb-1">
                        Comentario <span class="text-carbon/40 font-normal">(opcional)</span>
                    </label>
                    <textarea id="admin_comment" name="admin_comment"
                              class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600 resize-none h-32 overflow-y-auto"
                              placeholder="Ej: Se corrigio el problema de facturacion...">{{ old('admin_comment') }}</textarea>
                    @error('admin_comment')
                        <p class="mt-1 text-xs text-rust">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-6 py-2.5 rounded-md transition">
                        Marcar como resuelto
                    </button>
                </div>
            </form>
        @endif

        <a href="{{ route('admin.error-reports.index') }}" class="inline-flex text-sm text-ink-700 hover:text-brass-600 transition">
            &larr; Volver a reportes
        </a>
    </div>
</x-app-layout>

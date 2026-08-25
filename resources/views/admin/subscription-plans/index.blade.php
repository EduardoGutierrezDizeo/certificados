<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-serif text-2xl text-ink-700">Planes de suscripción</h2>
            <a href="{{ route('admin.subscription-plans.create') }}"
               class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-4 py-2 rounded-md transition">
                + Crear plan
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-4">

        @if (session('success'))
            <div class="bg-green-50 border border-green-600 text-green-700 text-sm rounded-lg px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white border border-ink-100 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface border-b border-ink-100">
                    <tr class="text-left text-xs font-medium text-carbon/50 uppercase tracking-wide">
                        <th class="px-5 py-3">Nombre</th>
                        <th class="px-5 py-3">Precio</th>
                        <th class="px-5 py-3">Duración</th>
                        <th class="px-5 py-3 text-center">Estado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="px-5 py-3.5 text-carbon font-medium">{{ $plan->name }}</td>
                            <td class="px-5 py-3.5 text-carbon/70">{{ $plan->formattedPrice() }}</td>
                            <td class="px-5 py-3.5 text-carbon/70">
                                {{ $plan->duration_months === 1 ? '1 mes' : "{$plan->duration_months} meses" }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if ($plan->is_active)
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-ink-50 text-ink-600">
                                        Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('admin.subscription-plans.edit', $plan) }}"
                                   class="text-xs font-medium text-ink-700 hover:underline">
                                    Editar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-carbon/50">
                                No hay planes de suscripción.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $plans->links() }}
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-serif text-2xl text-ink-700">Nueva cuenta de abogado</h2>
    </x-slot>

    <div class="max-w-xl">
        <form method="POST" action="{{ route('admin.lawyers.store') }}" class="bg-white border border-ink-100 rounded-lg p-6 space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-carbon mb-1">Nombre completo</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                @error('name') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-carbon mb-1">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                @error('email') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
            </div>

            @if ($plans->isEmpty())
                <div class="bg-brass-50 border border-brass-200 rounded-md p-4 text-sm text-brass-700">
                    No hay planes de suscripción activos. Debes
                    <a href="{{ route('admin.subscription-plans.create') }}" class="underline font-medium hover:text-brass-800">crear un plan</a>
                    antes de poder asignar cuentas a abogados.
                </div>
            @else
                <div>
                    <label for="subscription_plan_id" class="block text-sm font-medium text-carbon mb-1">Plan de suscripción</label>
                    <select id="subscription_plan_id" name="subscription_plan_id"
                            class="w-full rounded-md border-ink-100 text-sm focus:border-ink-600 focus:ring-ink-600">
                        <option value="">Seleccionar plan...</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" {{ old('subscription_plan_id') == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} — {{ $plan->formattedPrice() }} — {{ $plan->duration_months }} {{ $plan->duration_months === 1 ? 'mes' : 'meses' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-carbon/50">La suscripción se otorgará sin cobro. El abogado tendrá acceso inmediato.</p>
                    @error('subscription_plan_id') <p class="mt-1 text-xs text-rust">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="pt-2 flex justify-end">
                <button type="submit" {{ $plans->isEmpty() ? 'disabled' : '' }}
                        class="bg-ink-700 hover:bg-ink-800 text-white text-sm font-medium px-6 py-3 rounded-md transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Crear cuenta
                </button>
            </div>
        </form>
    </div>
</x-app-layout>

<div>
    @include('partials.admin-nav')

    <div class="card">
        <div class="border-b border-brand-50 px-4 py-3">
            <h2 class="font-bold text-stone-900">Organizadores</h2>
            <p class="text-xs text-stone-500">Marca en qué torneos puede organizar cada usuario. Sin ningún torneo marcado, solo puede ver, como cualquier visitante.</p>
        </div>
        <ul class="divide-y divide-brand-50">
            @forelse ($users as $user)
                @php $organized = $user->tournaments->pluck('id')->all(); @endphp
                <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center" wire:key="user-{{ $user->id }}">
                    <div class="min-w-0 sm:w-56">
                        <p class="font-semibold text-stone-900">{{ $user->name }}</p>
                        <p class="text-xs text-stone-400">
                            Alta {{ $user->created_at?->format('d/m H:i') }}
                            @unless ($organized) · <span class="font-semibold text-brand-700">pendiente de validar</span> @endunless
                        </p>
                    </div>
                    <div class="flex flex-1 flex-wrap gap-2">
                        @forelse ($tournaments as $tournament)
                            @php $isOrganizer = in_array($tournament->id, $organized); @endphp
                            <button wire:click="toggleOrganizer({{ $user->id }}, {{ $tournament->id }})"
                                    @class([
                                        'cursor-pointer rounded-xl px-3 py-1 text-sm ring-1 transition',
                                        'bg-brand-500 text-white ring-brand-500' => $isOrganizer,
                                        'bg-white text-stone-500 ring-brand-200 hover:ring-brand-400' => ! $isOrganizer,
                                    ])>{{ $isOrganizer ? '✓ ' : '' }}{{ $tournament->name }}</button>
                        @empty
                            <span class="text-sm text-stone-400">No hay torneos creados.</span>
                        @endforelse
                    </div>
                    <button wire:click="deleteUser({{ $user->id }})" wire:confirm="¿Borrar el usuario {{ $user->name }}?"
                            class="cursor-pointer self-start text-xs text-stone-400 hover:text-red-600 sm:self-center">Borrar</button>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-sm text-stone-500">
                    Aún no se ha registrado nadie. Quien vaya a organizar puede pedir acceso en
                    <span class="font-mono text-stone-700">{{ route('register') }}</span>.
                </li>
            @endforelse
        </ul>
    </div>
</div>

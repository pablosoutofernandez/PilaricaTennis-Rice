<div>
@include('partials.admin-nav')
<div class="grid gap-6 lg:grid-cols-[1fr_380px]">
    <section>

        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($tournaments as $t)
                <div class="card group relative overflow-hidden p-5 transition hover:border-brand-300" wire:key="t-{{ $t->id }}">
                    <div class="absolute -top-6 -right-6 size-20 rounded-full bg-ball-300/40"></div>
                    <a href="{{ route('tournaments.show', $t) }}" class="relative block">
                        <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">{{ $t->date->translatedFormat('j M Y') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-stone-900 group-hover:text-brand-700">{{ $t->name }}</h2>
                        <p class="mt-2 text-sm text-stone-500">{{ $t->pairs_count }} parejas · {{ $t->statusLabel() }}</p>
                        <p class="mt-1 text-xs text-stone-400">Organizan: {{ $t->organizers->pluck('name')->join(', ') ?: 'solo el administrador' }}</p>
                    </a>
                    <button wire:click="delete({{ $t->id }})"
                            wire:confirm="¿Borrar el torneo y todos sus datos?"
                            class="relative mt-3 cursor-pointer text-xs text-stone-400 hover:text-red-600">Borrar</button>
                </div>
            @empty
                <div class="card col-span-full p-10 text-center text-stone-500">
                    <p class="text-4xl">🎾</p>
                    <p class="mt-2">Aún no hay torneos. Crea el primero.</p>
                </div>
            @endforelse
        </div>
    </section>

    <aside class="card h-fit p-5">
        <h2 class="mb-4 font-bold text-brand-700">Nuevo torneo</h2>
        <form wire:submit="create" class="space-y-3">
            <div>
                <label class="label" for="name">Nombre</label>
                <input id="name" wire:model="name" class="input" placeholder="Torneo de Otoño">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="date">Fecha</label>
                <input id="date" type="date" wire:model="date" class="input">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="start">Empieza</label>
                    <input id="start" type="time" wire:model="start_time" class="input">
                </div>
                <div>
                    <label class="label" for="end">Debe acabar</label>
                    <input id="end" type="time" wire:model="end_time" class="input">
                </div>
            </div>
            @error('end_time') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <div>
                <label class="label" for="courts">Pistas</label>
                    <input id="courts" type="number" min="1" max="2" wire:model="courts" class="input">
            </div>
            <button class="btn-primary w-full">Crear torneo</button>
        </form>
    </aside>
</div>
</div>

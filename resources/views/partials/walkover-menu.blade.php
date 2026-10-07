<div x-data="{ open: false }" class="relative shrink-0">
    <button type="button" @click="open = ! open" class="{{ $buttonClass ?? 'btn-soft' }}" title="Una pareja no se presenta o no puede terminar">W.O.</button>
    <div x-show="open" x-cloak @click.outside="open = false" class="card absolute right-0 z-20 mt-1 w-64 p-2 text-left text-sm shadow-lg">
        <p class="px-2 pb-1 text-xs text-stone-500">¿Qué pareja no se presenta o no puede seguir? El partido lo gana la otra.</p>
        @foreach ([$m->pair1, $m->pair2] as $absent)
            <button type="button" wire:click="walkover({{ $m->id }}, {{ $absent->id }})"
                    wire:confirm="{{ $absent->name }} pierde este partido por W.O. ¿Seguro?"
                    class="block w-full cursor-pointer rounded-lg px-2 py-1.5 text-left hover:bg-red-50 hover:text-red-700">{{ $absent->name }}<x-pair-number :pair="$absent" /></button>
        @endforeach
        <p class="px-2 pt-1 text-[11px] text-stone-400">Si se va del torneo, retírala desde «Parejas».</p>
    </div>
</div>

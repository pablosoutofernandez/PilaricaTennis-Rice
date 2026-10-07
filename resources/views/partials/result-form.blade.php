<form wire:submit="save({{ $m->id }})" class="space-y-2 bg-white p-3 text-sm" wire:key="edit-{{ $m->id }}">
    <p class="text-xs text-stone-400">Corrigiendo · set desde {{ $m->startGames() }}-{{ $m->startGames() }}</p>
    <div class="grid grid-cols-[1fr_auto] items-center gap-2">
        <span class="truncate">{{ $m->pair1->name }}<x-pair-number :pair="$m->pair1" /></span>
        <input type="number" min="0" max="7" inputmode="numeric" class="score-input h-10 w-12 text-lg" wire:model="scores.{{ $m->id }}.g1" aria-label="Juegos {{ $m->pair1->name }}">
        <span class="truncate">{{ $m->pair2->name }}<x-pair-number :pair="$m->pair2" /></span>
        <input type="number" min="0" max="7" inputmode="numeric" class="score-input h-10 w-12 text-lg" wire:model="scores.{{ $m->id }}.g2" aria-label="Juegos {{ $m->pair2->name }}">
    </div>
    @error("score.{$m->id}") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    <div class="flex gap-2">
        <button class="btn-primary px-3 py-1.5">Guardar</button>
        <button type="button" wire:click="cancelEdit" class="btn-ghost px-3 py-1.5">Cancelar</button>
    </div>
</form>

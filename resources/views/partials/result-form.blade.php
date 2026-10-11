<form wire:submit="save({{ $m->id }})" class="space-y-2 bg-white p-3 text-sm" wire:key="edit-{{ $keyPrefix ?? '' }}{{ $m->id }}">
    <p class="text-xs text-stone-400">Corrigiendo · {{ $m->isTwoSets() ? '2 sets + súper tie-break' : 'set desde '.\App\Services\FormatPlanner::startLabel($m->startGames()) }}</p>
    @include('partials.score-fields', ['compact' => true])
    @error("score.{$m->id}") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    <div class="flex gap-2">
        <button class="btn-primary px-3 py-1.5">Guardar</button>
        <button type="button" wire:click="cancelEdit" class="btn-ghost px-3 py-1.5">Cancelar</button>
    </div>
</form>

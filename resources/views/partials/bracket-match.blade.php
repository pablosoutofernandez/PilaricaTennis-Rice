<div @class([
        'card overflow-hidden text-sm',
        'ring-2 ring-ball-400' => $m->status === 'playing',
    ]) wire:key="ko-{{ $keyPrefix ?? '' }}{{ $m->id }}">
    @if ($editing === $m->id)
        @include('partials.result-form')
    @else
    @foreach ([[$m->pair1, $m->games1, $m->pair1_id], [$m->pair2, $m->games2, $m->pair2_id]] as [$pair, $games, $pairId])
        <div @class([
                'flex items-center gap-2 px-3 py-2',
                'border-t border-brand-50' => $loop->last,
                'bg-brand-50 font-bold text-stone-900' => $m->winner_id && $m->winner_id === $pairId,
                'text-stone-400' => $m->winner_id && $m->winner_id !== $pairId,
            ])>
            <span @class(['min-w-0 flex-1 leading-snug break-words md:truncate', 'line-through' => $pair?->isWithdrawn()])>{{ $pair?->name ?? 'Por decidir' }}<x-pair-number :pair="$pair" /></span>
            @if ($m->status === 'finished' && $m->walkover)
                @if ($m->winner_id !== $pairId)
                    <span class="text-xs font-bold text-red-600">W.O.</span>
                @endif
            @elseif ($m->status === 'finished')
                <span class="w-6 text-center font-bold text-brand-700">{{ $games }}</span>
            @endif
        </div>
    @endforeach
    @if ($m->status === 'playing')
        <div class="bg-ball-300 px-3 py-0.5 text-center text-xs font-bold text-stone-800">En juego · Pista {{ $m->court }}</div>
    @elseif ($m->status === 'finished' && auth()->user()?->can('manage', $tournament))
        <button type="button" wire:click="edit({{ $m->id }})" class="block w-full cursor-pointer border-t border-brand-50 py-0.5 text-center text-[11px] text-stone-400 hover:bg-brand-50 hover:text-brand-700">Corregir</button>
    @endif
    @endif
</div>

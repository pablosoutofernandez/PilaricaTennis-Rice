{{--
    Casillas del marcador de $m: un set o, a 2 sets, cada set y el súper tie-break.
    $compact: más pequeñas (corrección dentro de Grupos o Cuadro). $live: se guardan al teclear.
--}}
@php
    $compact ??= false;
    $model = ($live ?? false) ? 'wire:model.live' : 'wire:model';
@endphp
@if ($m->isTwoSets())
    @php $input = $compact ? 'score-input h-10 w-10 text-lg' : 'score-input h-11 w-11 text-xl'; @endphp
    <div class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-x-1.5 gap-y-2">
        <span></span>
        @foreach (['Set 1', 'Set 2', 'Súper TB'] as $heading)
            <span class="text-center text-[11px] leading-tight font-semibold text-stone-400">{{ $heading }}</span>
        @endforeach
        @foreach ([$m->pair1, $m->pair2] as $side => $pair)
            <span class="leading-snug font-semibold break-words">{{ $pair->name }}<x-pair-number :pair="$pair" /></span>
            @foreach (range(0, 2) as $set)
                <input type="number" min="0" max="{{ $set === 2 ? 30 : 7 }}" inputmode="numeric" class="{{ $input }}"
                       {{ $model }}="scores.{{ $m->id }}.sets.{{ $set }}.{{ $side }}"
                       aria-label="{{ $set === 2 ? 'Súper tie-break' : 'Set '.($set + 1) }} · {{ $pair->name }}">
            @endforeach
        @endforeach
    </div>
    <p class="mt-1 text-[11px] text-stone-400">El súper tie-break, solo si quedan 1-1 en sets.</p>
@else
    @php $input = $compact ? 'score-input h-10 w-12 text-lg' : 'score-input'; @endphp
    <div class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-2">
        @foreach (['g1' => $m->pair1, 'g2' => $m->pair2] as $field => $pair)
            <span class="leading-snug font-semibold break-words">{{ $pair->name }}<x-pair-number :pair="$pair" /></span>
            <input type="number" min="0" max="7" inputmode="numeric" class="{{ $input }}"
                   {{ $model }}="scores.{{ $m->id }}.{{ $field }}" aria-label="Juegos {{ $pair->name }}">
        @endforeach
    </div>
@endif

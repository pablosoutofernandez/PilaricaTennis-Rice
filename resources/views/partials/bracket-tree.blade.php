{{--
    Cuadro en formato de llaves. Cada ronda reparte la altura a partes iguales entre sus huecos,
    así cada partido queda centrado entre los dos que lo alimentan y las líneas encajan.
    $rounds: rondas con 'name' y 'slots' (partido o null si es un bye) · $tone: brand | ball
--}}
@php
    $line = $tone === 'ball' ? 'border-ball-400' : 'border-brand-300';
    $heading = $tone === 'ball' ? 'bg-ball-100 text-ball-700' : 'bg-brand-100 text-brand-800';
    $previousSlots = null;
@endphp
{{-- En el móvil: ronda a ronda, de arriba abajo --}}
<div class="space-y-5 md:hidden">
    @foreach ($rounds as $size => $round)
        <section wire:key="{{ $key }}-list-{{ $size }}">
            <h3 class="mb-2 rounded-xl px-3 py-1.5 text-sm font-bold {{ $heading }}">{{ $round['name'] }}</h3>
            <div class="space-y-2">
                @foreach (array_filter($round['slots']) as $m)
                    @include('partials.bracket-match', ['m' => $m, 'keyPrefix' => 'list-'])
                @endforeach
            </div>
        </section>
    @endforeach
</div>

{{-- En pantallas grandes: llaves --}}
<div class="hidden overflow-x-auto pb-4 md:block">
    <div class="flex min-w-max gap-6">
        @foreach ($rounds as $size => $round)
            @php $isLastRound = $loop->last; @endphp
            <div class="flex w-60 flex-col" wire:key="{{ $key }}-round-{{ $size }}">
                <h3 class="mb-2 rounded-xl px-3 py-1.5 text-center text-sm font-bold {{ $heading }}">{{ $round['name'] }}</h3>
                <div class="flex flex-1 flex-col">
                    @foreach ($round['slots'] as $index => $m)
                        <div class="relative flex min-h-24 flex-1 items-center py-2">
                            @if ($m)
                                @if ($previousSlots && (($previousSlots[$index * 2] ?? null) || ($previousSlots[$index * 2 + 1] ?? null)))
                                    <span class="absolute top-1/2 -left-3 w-3 border-t-2 {{ $line }}"></span>
                                @endif
                                <div class="w-full">@include('partials.bracket-match', ['m' => $m])</div>
                                @if (! $isLastRound || $champion !== false)
                                    <span class="absolute top-1/2 -right-3 w-3 border-t-2 {{ $line }}"></span>
                                @endif
                                @if (! $isLastRound)
                                    <span @class(['absolute -right-3 border-r-2', $line, 'top-1/2 bottom-0' => $index % 2 === 0, 'top-0 bottom-1/2' => $index % 2 === 1])></span>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @php $previousSlots = $round['slots']; @endphp
        @endforeach

        @if ($champion !== false)
            <div class="flex w-52 flex-col">
                <h3 class="mb-2 rounded-xl bg-ball-300 px-3 py-1.5 text-center text-sm font-bold text-stone-800">{{ $championLabel }}</h3>
                <div class="relative flex flex-1 items-center py-2">
                    <span class="absolute top-1/2 -left-3 w-3 border-t-2 {{ $line }}"></span>
                    <div @class([
                            'card w-full px-3 py-3 text-center text-sm',
                            'border-ball-300 bg-ball-100 font-bold text-stone-900' => $champion,
                            'text-stone-400' => ! $champion,
                        ])>
                        @if ($champion)
                            🏆 {{ $champion->name }}<x-pair-number :pair="$champion" />
                        @else
                            Por decidir
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

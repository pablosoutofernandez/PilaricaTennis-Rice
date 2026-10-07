<div wire:poll.15s>
    @include('partials.tournament-nav')

    @if ($rounds->isEmpty() && $consolationRounds->isEmpty() && ! $consolationChampion)
        <div class="card p-10 text-center text-stone-500">
            <p class="text-4xl">🏆</p>
            <p class="mt-2">El cuadro se genera automáticamente al terminar la fase de grupos.</p>
            @if ($tournament->qualifiers)
                <p class="mt-1 text-sm">Se clasifican {{ $tournament->qualifiers }} parejas.</p>
            @endif
        </div>
    @else
        @if ($champion)
            <div class="card mb-6 flex items-center gap-4 overflow-hidden border-ball-300 bg-gradient-to-r from-ball-100 via-white to-brand-50 p-5">
                <span class="grid size-14 place-items-center rounded-full bg-ball-300 text-3xl shadow-inner">🏆</span>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Campeones</p>
                    <p class="text-2xl font-bold text-stone-900">{{ $champion->name }}</p>
                </div>
            </div>
        @endif

        @if ($rounds->isNotEmpty())
            <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Cuadro principal</h2>
            <div class="overflow-x-auto pb-4">
                <div class="flex min-w-max gap-6">
                    @foreach ($rounds as $size => $round)
                        <div class="flex w-64 flex-col" wire:key="main-round-{{ $size }}">
                            <h3 class="mb-3 rounded-xl bg-brand-100 px-3 py-1.5 text-center text-sm font-bold text-brand-800">{{ $round['name'] }}</h3>
                            <div class="flex flex-1 flex-col justify-around gap-4">
                                @foreach ($round['matches'] as $m)
                                    @include('partials.bracket-match', ['m' => $m])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @if ($thirdPlace)
                        <div class="flex w-64 flex-col justify-end">
                            <h3 class="mb-3 rounded-xl bg-stone-100 px-3 py-1.5 text-center text-sm font-bold text-stone-600">3er y 4º puesto</h3>
                            @include('partials.bracket-match', ['m' => $thirdPlace])
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($consolationRounds->isNotEmpty() || $consolationChampion)
            <section class="mt-8 border-t border-brand-100 pt-6">
                <h2 class="mb-3 text-sm font-bold tracking-wide text-ball-600 uppercase">Cuadro de consolación</h2>
                @if ($consolationChampion)
                    <div class="card mb-5 flex items-center gap-4 border-ball-300 bg-ball-50 p-4">
                        <span class="grid size-12 place-items-center rounded-full bg-ball-300 text-2xl">🏆</span>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-ball-600 uppercase">Campeón de consolación</p>
                            <p class="text-xl font-bold text-stone-900">{{ $consolationChampion->name }}</p>
                        </div>
                    </div>
                @endif
                <div class="overflow-x-auto pb-4">
                    <div class="flex min-w-max gap-6">
                        @foreach ($consolationRounds as $size => $round)
                            <div class="flex w-64 flex-col" wire:key="consolation-round-{{ $size }}">
                                <h3 class="mb-3 rounded-xl bg-ball-100 px-3 py-1.5 text-center text-sm font-bold text-ball-700">{{ $round['name'] }}</h3>
                                <div class="flex flex-1 flex-col justify-around gap-4">
                                    @foreach ($round['matches'] as $m)
                                        @include('partials.bracket-match', ['m' => $m])
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>

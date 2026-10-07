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
                    <p class="text-2xl font-bold text-stone-900">{{ $champion->name }}<x-pair-number :pair="$champion" /></p>
                </div>
            </div>
        @endif

        @if ($rounds->isNotEmpty())
            <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Cuadro principal</h2>
            @include('partials.bracket-tree', ['rounds' => $rounds, 'tone' => 'brand', 'key' => 'main', 'champion' => $champion, 'championLabel' => 'Campeones'])
            @if ($thirdPlace)
                <div class="mt-4 w-60">
                    <h3 class="mb-2 rounded-xl bg-stone-100 px-3 py-1.5 text-center text-sm font-bold text-stone-600">3er y 4º puesto</h3>
                    @include('partials.bracket-match', ['m' => $thirdPlace])
                </div>
            @endif
        @endif

        @if ($consolationRounds->isNotEmpty() || $consolationChampion)
            <section class="mt-8 border-t border-brand-100 pt-6">
                <h2 class="mb-3 text-sm font-bold tracking-wide text-ball-600 uppercase">Cuadro de consolación</h2>
                @if ($consolationChampion)
                    <div class="card mb-5 flex items-center gap-4 border-ball-300 bg-ball-50 p-4">
                        <span class="grid size-12 place-items-center rounded-full bg-ball-300 text-2xl">🏆</span>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-ball-600 uppercase">Campeón de consolación</p>
                            <p class="text-xl font-bold text-stone-900">{{ $consolationChampion->name }}<x-pair-number :pair="$consolationChampion" /></p>
                        </div>
                    </div>
                @endif
                @if ($consolationRounds->isNotEmpty())
                    @include('partials.bracket-tree', ['rounds' => $consolationRounds, 'tone' => 'ball', 'key' => 'consolation', 'champion' => $consolationChampion, 'championLabel' => 'Campeón de consolación'])
                @endif
            </section>
        @endif
    @endif
</div>

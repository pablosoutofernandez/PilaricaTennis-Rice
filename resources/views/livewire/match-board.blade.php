<div wire:poll.10s>
    @include('partials.tournament-nav')

    @if ($tournament->isRegistration())
        <div class="card p-10 text-center text-stone-500">
            <p class="text-4xl">🎾</p>
            <p class="mt-2">Los partidos aparecerán cuando empiece el torneo.</p>
            <a href="{{ route('tournaments.pairs', $tournament) }}" wire:navigate class="btn-primary mt-4">Ir a parejas</a>
        </div>
    @else
        {{-- Progreso y marcador inicial --}}
        <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="h-2.5 w-48 overflow-hidden rounded-full bg-brand-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-ball-400" style="width: {{ $total ? round($finished->count() / $total * 100) : 0 }}%"></div>
                </div>
                <span class="text-sm text-stone-500">{{ $finished->count() }}/{{ $total }} partidos jugados</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach (['groups' => 'Grupos', 'knockout' => 'Fase final'] as $stage => $label)
                    <span class="text-stone-500">{{ $label }} desde</span>
                    <div class="flex overflow-hidden rounded-xl ring-1 ring-brand-200">
                        @foreach (range(0, 4) as $g)
                            <button wire:click="setStartGames('{{ $stage }}', {{ $g }})"
                                    @class([
                                        'cursor-pointer px-2 py-1 text-xs font-semibold',
                                        'bg-brand-500 text-white' => (int) $tournament->{"start_games_$stage"} === $g,
                                        'bg-white text-stone-500 hover:bg-brand-50' => (int) $tournament->{"start_games_$stage"} !== $g,
                                    ])>{{ $g }}-{{ $g }}</button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        @error('court') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

        {{-- Pistas --}}
        <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">En juego</h2>
        <div class="mb-8 grid gap-4 md:grid-cols-2">
            @foreach (range(1, $tournament->courts) as $court)
                @php $m = $playing->get($court); @endphp
                <div class="card overflow-hidden" wire:key="court-{{ $court }}-{{ $m?->id }}">
                    <div class="flex items-center justify-between bg-gradient-to-r from-brand-500 to-brand-400 px-4 py-2 text-white">
                        <span class="font-bold">Pista {{ $court }}</span>
                        @if ($m)
                            <span class="text-xs font-semibold">
                                {{ $m->stageLabel() }} · desde {{ $m->started_at->format('H:i') }}
                                ({{ (int) $m->started_at->diffInMinutes(now()) }}′)
                            </span>
                        @endif
                    </div>

                    @if ($m)
                        <form wire:submit="save({{ $m->id }})" class="p-4">
                            <p class="mb-3 text-center text-xs text-stone-500">
                                Set empezando <span class="badge bg-ball-300 text-stone-800">{{ $m->startGames() }}-{{ $m->startGames() }}</span>
                            </p>
                            <div class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-2">
                                <span class="font-semibold">{{ $m->pair1->name }}</span>
                                <input type="number" min="0" max="7" inputmode="numeric" class="score-input"
                                       wire:model.live="scores.{{ $m->id }}.g1" aria-label="Juegos {{ $m->pair1->name }}">
                                <span class="font-semibold">{{ $m->pair2->name }}</span>
                                <input type="number" min="0" max="7" inputmode="numeric" class="score-input"
                                       wire:model.live="scores.{{ $m->id }}.g2" aria-label="Juegos {{ $m->pair2->name }}">
                            </div>
                            @error("score.{$m->id}") <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-4 flex gap-2">
                                <button class="btn-primary flex-1">Guardar resultado</button>
                                <button type="button" wire:click="postpone({{ $m->id }})"
                                        wire:confirm="¿Retirar este partido de la pista y aplazarlo?"
                                        class="btn-soft">Aplazar</button>
                            </div>
                        </form>
                    @else
                        @php $nextReady = $upcoming->first(fn ($u) => ! in_array($u->pair1_id, $busy) && ! in_array($u->pair2_id, $busy)); @endphp
                        <div class="p-6 text-center">
                            <p class="text-stone-500">Pista libre</p>
                            @if ($nextReady)
                                <button wire:click="callToCourt({{ $nextReady->id }}, {{ $court }})" class="btn-ball mt-3">
                                    Llamar: {{ $nextReady->pair1->name }} vs {{ $nextReady->pair2->name }}
                                </button>
                            @elseif ($tournament->status === 'finished')
                                <p class="mt-2 text-sm text-brand-700">Torneo terminado 🏆</p>
                            @else
                                <p class="mt-2 text-xs text-stone-400">No hay partidos listos para esta pista.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Próximos --}}
            <section>
                <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Próximos partidos</h2>
                <div class="card divide-y divide-brand-50">
                    @forelse ($upcoming as $u)
                        @php $ready = ! in_array($u->pair1_id, $busy) && ! in_array($u->pair2_id, $busy); @endphp
                        <div @class(['flex items-center gap-3 px-4 py-3', 'bg-ball-100' => $loop->first]) wire:key="up-{{ $u->id }}">
                            <div class="w-12 shrink-0 text-center">
                                @if ($loop->first)
                                    <span class="block text-[10px] font-bold tracking-wide text-ball-600 uppercase">Siguiente</span>
                                @endif
                                <span class="text-sm font-bold text-brand-700">{{ $eta[$u->id] ?? '' }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-stone-400">
                                    {{ $u->stageLabel() }}
                                    @if ($u->postponed) · <span class="text-brand-600">aplazado ×{{ $u->postponed }}</span> @endif
                                    @unless ($ready) · <span class="text-stone-500">esperando a que acabe su partido</span> @endunless
                                </p>
                                <p class="truncate text-sm font-semibold">{{ $u->pair1->name }} <span class="font-normal text-stone-400">vs</span> {{ $u->pair2->name }}</p>
                            </div>
                            <button wire:click="postpone({{ $u->id }})" class="btn-ghost shrink-0 px-2 py-1 text-xs" title="Retrasar {{ config('torneo.postpone_steps') }} puestos">Aplazar</button>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-stone-500">No hay más partidos en cola.</p>
                    @endforelse
                    @if ($waiting)
                        <p class="px-4 py-2 text-xs text-stone-400">+ {{ $waiting }} partido(s) de la fase final pendientes de rival.</p>
                    @endif
                </div>
            </section>

            {{-- Resultados --}}
            <section>
                <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Resultados</h2>
                <div class="card divide-y divide-brand-50">
                    @forelse ($finished as $f)
                        <div class="px-4 py-2.5" wire:key="fin-{{ $f->id }}">
                            @if ($editing === $f->id)
                                <form wire:submit="save({{ $f->id }})" class="space-y-2">
                                    <p class="text-xs text-stone-400">{{ $f->stageLabel() }} · corrigiendo (set desde {{ $f->startGames() }}-{{ $f->startGames() }})</p>
                                    <div class="grid grid-cols-[1fr_auto] items-center gap-2">
                                        <span class="text-sm">{{ $f->pair1->name }}</span>
                                        <input type="number" min="0" max="7" class="score-input h-10 w-12 text-lg" wire:model.live="scores.{{ $f->id }}.g1">
                                        <span class="text-sm">{{ $f->pair2->name }}</span>
                                        <input type="number" min="0" max="7" class="score-input h-10 w-12 text-lg" wire:model.live="scores.{{ $f->id }}.g2">
                                    </div>
                                    @error("score.{$f->id}") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                    <div class="flex gap-2">
                                        <button class="btn-primary px-3 py-1.5">Guardar</button>
                                        <button type="button" wire:click="cancelEdit" class="btn-ghost px-3 py-1.5">Cancelar</button>
                                    </div>
                                </form>
                            @else
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs text-stone-400">{{ $f->stageLabel() }} · Pista {{ $f->court }} · {{ $f->finished_at?->format('H:i') }}</p>
                                        <p class="truncate text-sm">
                                            <span @class(['font-bold' => $f->winner_id === $f->pair1_id])>{{ $f->pair1->name }}</span>
                                            <span class="text-stone-400">vs</span>
                                            <span @class(['font-bold' => $f->winner_id === $f->pair2_id])>{{ $f->pair2->name }}</span>
                                        </p>
                                    </div>
                                    <span class="rounded-lg bg-brand-50 px-2 py-1 text-sm font-bold text-brand-700">{{ $f->games1 }}-{{ $f->games2 }}</span>
                                    <button wire:click="edit({{ $f->id }})" class="btn-ghost px-2 py-1 text-xs">Editar</button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-stone-500">Todavía no hay resultados.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</div>

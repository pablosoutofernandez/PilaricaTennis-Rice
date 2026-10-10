<div wire:poll.10s>
    @include('partials.tournament-nav')

    @if ($tournament->isRegistration())
        <div class="card p-8 text-center text-stone-500 md:p-10">
            <p class="text-4xl">🎾</p>
            <p class="mt-2">Los partidos aparecerán cuando empiece el torneo.</p>
            <a href="{{ route('tournaments.pairs', $tournament) }}" wire:navigate class="btn-primary mt-4">Ir a parejas</a>
        </div>
    @else
        {{-- Progreso y marcador inicial --}}
        <div class="mb-5 flex flex-col gap-3 md:mb-6 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-brand-100 md:w-48 md:flex-none">
                    <div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-ball-400" style="width: {{ $total ? round($played / $total * 100) : 0 }}%"></div>
                </div>
                <span class="shrink-0 text-sm text-stone-500">{{ $played }}/{{ $total }} jugados</span>
            </div>
            @if ($canManage && $tournament->status === 'groups')
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-stone-500">Grupos desde</span>
                    <div class="flex overflow-hidden rounded-xl ring-1 ring-brand-200">
                        @foreach (range(0, 4) as $g)
                            <button wire:click="setStartGames('groups', {{ $g }})"
                                    @class([
                                        'cursor-pointer px-2.5 py-1.5 text-xs font-semibold',
                                        'bg-brand-500 text-white' => (int) $tournament->start_games_groups === $g,
                                        'bg-white text-stone-500 hover:bg-brand-50' => (int) $tournament->start_games_groups !== $g,
                                    ])>{{ $g }}-{{ $g }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Marcador inicial de la fase final: se puede acortar si se va mal de tiempo --}}
        @if ($canManage && $knockoutStartOptions)
            <div @class([
                    'card mb-5 p-3 md:mb-6 md:p-4',
                    'border-ball-300 bg-ball-100' => $tournament->status === 'knockout',
                ])>
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                    <h2 class="font-bold text-stone-900">Fase final: ¿a cuánto se empieza?</h2>
                    <p class="text-xs text-stone-500">Hora de fin estimada con cada marcador</p>
                </div>
                <div class="grid grid-cols-5 gap-1.5 md:gap-2">
                    @foreach ($knockoutStartOptions as $g => $finishAt)
                        @php $isCurrent = (int) $tournament->start_games_knockout === $g; @endphp
                        <button wire:click="setStartGames('knockout', {{ $g }})"
                                @if (! $isCurrent) wire:confirm="Los partidos de la fase final que faltan empezarán {{ $g }}-{{ $g }}. ¿Cambiar?" @endif
                                @class([
                                    'cursor-pointer rounded-xl px-1 py-2 text-center ring-1 transition',
                                    'bg-brand-500 text-white ring-brand-500' => $isCurrent,
                                    'bg-white text-stone-700 ring-brand-200 hover:ring-brand-400' => ! $isCurrent,
                                ])>
                            <span class="block text-sm font-bold">{{ $g }}-{{ $g }}</span>
                            <span @class(['block text-[11px] md:text-xs', 'text-white/80' => $isCurrent, 'text-stone-500' => ! $isCurrent])><span class="hidden md:inline">fin </span>≈ {{ $finishAt->format('H:i') }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @error('court') <p class="mb-4 text-sm text-red-600">{{ $message }}</p> @enderror

        @if (! $canManage && $tournament->status !== 'finished' && $organizerNames->isNotEmpty())
            <p class="card mb-4 border-ball-300 bg-ball-100 px-4 py-3 text-sm text-stone-700">
                Para publicar un resultado, ponte en contacto con <strong>{{ $organizerNames->join(', ', ' o ') }}</strong>.
            </p>
        @endif

        {{-- Pistas: lo que se juega ahora --}}
        <div class="mb-6 grid gap-4 md:mb-8 md:grid-cols-2">
            @foreach (range(1, $tournament->courts) as $court)
                @php
                    $m = $playing->get($court);
                    $closed = $tournament->isCourtClosed($court);
                @endphp
                <div class="card flex flex-col overflow-hidden" wire:key="court-{{ $court }}-{{ $m?->id }}-{{ $closed ? 'closed' : 'open' }}">
                    <div @class([
                            'flex items-center justify-between gap-2 px-4 py-2.5 text-white',
                            'bg-gradient-to-r from-brand-500 to-brand-400' => ! $closed,
                            'bg-stone-400' => $closed,
                        ])>
                        <span class="text-lg font-black">Pista {{ $court }}</span>
                        <span class="flex items-center gap-2 text-xs font-semibold">
                            @if ($closed)
                                <span>Cerrada</span>
                            @elseif ($m)
                                <span class="rounded-full bg-white/20 px-2 py-0.5">⏱ {{ (int) $m->started_at->diffInMinutes(now()) }}′</span>
                            @endif
                            @if ($canManage && $tournament->status !== 'finished' && ! $closed)
                                <button wire:click="closeCourt({{ $court }})"
                                        wire:confirm="{{ $m ? 'El partido que se está jugando volverá el primero a la cola. ' : '' }}¿Cerrar la pista {{ $court }} (lluvia, avería...)?"
                                        class="cursor-pointer rounded-lg bg-white/20 px-2 py-1 hover:bg-white/30">Cerrar</button>
                            @endif
                        </span>
                    </div>

                    @if ($closed)
                        <div class="flex-1 p-5 text-center">
                            <p class="text-stone-500">No se le asignan partidos.</p>
                            @if ($canManage)
                                <button wire:click="openCourt({{ $court }})" class="btn-primary mt-3">Reabrir pista</button>
                            @endif
                        </div>
                    @elseif ($m && ! $canManage)
                        <div class="flex-1 px-4 py-4 text-center">
                            <p class="mb-2 text-xs text-stone-500">{{ $m->stageLabel() }} · desde las {{ $m->started_at->format('H:i') }}</p>
                            <p class="text-lg leading-snug font-bold break-words text-stone-900">{{ $m->pair1->name }}<x-pair-number :pair="$m->pair1" /></p>
                            <p class="my-1 text-xs font-bold text-ball-600">VS</p>
                            <p class="text-lg leading-snug font-bold break-words text-stone-900">{{ $m->pair2->name }}<x-pair-number :pair="$m->pair2" /></p>
                        </div>
                    @elseif ($m)
                        <form wire:submit="save({{ $m->id }})" class="flex-1 p-4">
                            <p class="mb-3 text-center text-xs text-stone-500">
                                {{ $m->stageLabel() }} · desde las {{ $m->started_at->format('H:i') }} · set desde
                                <span class="badge bg-ball-300 text-stone-800">{{ $m->startGames() }}-{{ $m->startGames() }}</span>
                            </p>
                            <div class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-2">
                                <span class="leading-snug font-semibold break-words">{{ $m->pair1->name }}<x-pair-number :pair="$m->pair1" /></span>
                                <input type="number" min="0" max="7" inputmode="numeric" class="score-input"
                                       wire:model.live="scores.{{ $m->id }}.g1" aria-label="Juegos {{ $m->pair1->name }}">
                                <span class="leading-snug font-semibold break-words">{{ $m->pair2->name }}<x-pair-number :pair="$m->pair2" /></span>
                                <input type="number" min="0" max="7" inputmode="numeric" class="score-input"
                                       wire:model.live="scores.{{ $m->id }}.g2" aria-label="Juegos {{ $m->pair2->name }}">
                            </div>
                            @error("score.{$m->id}") <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-4 flex gap-2">
                                <button class="btn-primary flex-1 py-2.5">Guardar resultado</button>
                                <button type="button" wire:click="postpone({{ $m->id }})"
                                        wire:confirm="¿Retirar este partido de la pista y aplazarlo?"
                                        class="btn-soft">Aplazar</button>
                                @include('partials.walkover-menu')
                            </div>
                        </form>
                    @else
                        @php $nextReady = $upcoming->first(fn ($u) => ! in_array($u->pair1_id, $busy) && ! in_array($u->pair2_id, $busy)); @endphp
                        <div class="flex-1 p-5 text-center">
                            <p class="text-stone-500">Pista libre</p>
                            @if ($nextReady && $canManage)
                                <button wire:click="callToCourt({{ $nextReady->id }}, {{ $court }})" class="btn-ball mt-3 h-auto w-full py-2.5 whitespace-normal">
                                    Llamar: {{ $nextReady->pair1->name }}<x-pair-number :pair="$nextReady->pair1" /> vs {{ $nextReady->pair2->name }}<x-pair-number :pair="$nextReady->pair2" />
                                </button>
                            @elseif ($tournament->status === 'finished')
                                <p class="mt-2 text-sm text-brand-700">Torneo terminado 🏆</p>
                            @endif
                        </div>
                    @endif

                </div>
            @endforeach
        </div>

        {{-- Los dos siguientes: entran en la primera pista que quede libre. Lo más consultado, siempre entero. --}}
        @unless ($tournament->status === 'finished')
            <section class="mb-6 md:mb-8">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-3">
                    <h2 class="text-sm font-bold tracking-wide text-brand-700 uppercase">Siguientes</h2>
                    <p class="text-xs text-stone-500">En la primera pista que quede libre</p>
                </div>
                @if ($nextUp->isEmpty())
                    <p class="card px-4 py-5 text-center text-sm text-stone-500">Sin siguientes partidos{{ $waiting ? ' hasta que se decidan los rivales del cuadro' : '' }}.</p>
                @else
                    <div class="grid gap-3 md:grid-cols-2 md:gap-4">
                        @foreach ($nextUp as $next)
                            <div class="card border-ball-300 bg-ball-100 px-4 py-3" wire:key="next-{{ $next->id }}">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="text-xs font-black tracking-wide text-ball-600 uppercase">{{ $loop->first ? '1.º' : '2.º' }} siguiente</span>
                                    @if (isset($eta[$next->id]))
                                        <span class="text-base font-bold text-stone-900">≈ {{ $eta[$next->id]->format('H:i') }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-stone-500">
                                    {{ $next->stageLabel() }}
                                    @if ($next->postponed) · <span class="text-brand-600">aplazado ×{{ $next->postponed }}</span> @endif
                                    @if (array_intersect([$next->pair1_id, $next->pair2_id], $busy)) · <span class="text-brand-700">esperando a que acabe su partido</span> @endif
                                </p>
                                <p class="mt-1.5 text-base leading-snug font-bold break-words text-stone-900">{{ $next->pair1->name }}<x-pair-number :pair="$next->pair1" /></p>
                                <p class="text-xs text-stone-400">vs</p>
                                <p class="text-base leading-snug font-bold break-words text-stone-900">{{ $next->pair2->name }}<x-pair-number :pair="$next->pair2" /></p>
                                @if ($canManage)
                                    <div class="mt-2 flex justify-end gap-1">
                                        <button wire:click="postpone({{ $next->id }})" class="btn-ghost px-2.5 py-1.5 text-xs" title="Retrasar {{ config('torneo.postpone_steps') }} puestos">Aplazar</button>
                                        @include('partials.walkover-menu', ['m' => $next, 'buttonClass' => 'btn-ghost px-2.5 py-1.5 text-xs'])
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endunless

        {{-- Resto de la cola --}}
        <section>
            <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Después</h2>
            <div class="card divide-y divide-brand-50">
                @forelse ($later as $u)
                    @php $ready = ! in_array($u->pair1_id, $busy) && ! in_array($u->pair2_id, $busy); @endphp
                    <div class="flex gap-3 px-4 py-3" wire:key="up-{{ $u->id }}">
                        <span class="w-12 shrink-0 pt-0.5 text-sm font-bold text-brand-700">{{ isset($eta[$u->id]) ? '≈ '.$eta[$u->id]->format('H:i') : '–' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-stone-400">
                                {{ $u->stageLabel() }}
                                @if ($u->postponed) · <span class="text-brand-600">aplazado ×{{ $u->postponed }}</span> @endif
                                @unless ($ready) · <span class="text-stone-500">esperando a que acabe su partido</span> @endunless
                            </p>
                            <p class="text-sm leading-snug font-semibold break-words">{{ $u->pair1->name }}<x-pair-number :pair="$u->pair1" /> <span class="font-normal text-stone-400">vs</span></p>
                            <p class="text-sm leading-snug font-semibold break-words">{{ $u->pair2->name }}<x-pair-number :pair="$u->pair2" /></p>
                            @if ($canManage)
                                <div class="mt-1.5 flex justify-end gap-1 md:hidden">
                                    <button wire:click="postpone({{ $u->id }})" class="btn-ghost px-2.5 py-1.5 text-xs">Aplazar</button>
                                    @include('partials.walkover-menu', ['m' => $u, 'buttonClass' => 'btn-ghost px-2.5 py-1.5 text-xs'])
                                </div>
                            @endif
                        </div>
                        @if ($canManage)
                            <div class="hidden shrink-0 items-start gap-1 md:flex">
                                <button wire:click="postpone({{ $u->id }})" class="btn-ghost px-2 py-1 text-xs" title="Retrasar {{ config('torneo.postpone_steps') }} puestos">Aplazar</button>
                                @include('partials.walkover-menu', ['m' => $u, 'buttonClass' => 'btn-ghost px-2 py-1 text-xs'])
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-500">No hay más partidos en cola.</p>
                @endforelse
                @if ($waiting)
                    <p class="px-4 py-2 text-xs text-stone-400">+ {{ $waiting }} partido(s) del cuadro pendientes de rival.</p>
                @endif
            </div>
            @if ($canManage)
                <p class="mt-2 text-xs text-stone-400">
                    Los resultados están en
                    <a href="{{ route('tournaments.groups', $tournament) }}" wire:navigate class="text-brand-700 underline">Grupos</a> y
                    <a href="{{ route('tournaments.bracket', $tournament) }}" wire:navigate class="text-brand-700 underline">Cuadro</a>; allí también se corrigen.
                </p>
            @endif
        </section>
    @endif
</div>

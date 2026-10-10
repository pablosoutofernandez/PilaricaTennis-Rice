<div wire:poll.15s class="space-y-6 md:space-y-8">
    @if (session('status'))
        <p class="card border-ball-300 bg-ball-100 px-4 py-3 text-sm text-stone-700">{{ session('status') }}</p>
    @endif

    @if (! $featured)
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-500 via-brand-400 to-brand-300 px-6 py-20 text-center text-white shadow-xl shadow-brand-200">
            <div class="absolute -top-16 -right-16 size-64 rounded-full bg-ball-300/40"></div>
            <div class="absolute -bottom-20 -left-10 size-56 rounded-full border-[18px] border-white/15"></div>
            <p class="relative text-6xl">🎾</p>
            <h1 class="relative mt-4 text-4xl font-black tracking-tight">Muy pronto, torneo</h1>
        </section>
    @else
        @php
            $statusText = [
                'registration' => 'Inscripción abierta',
                'groups' => 'En juego',
                'knockout' => 'En juego',
                'finished' => 'Finalizado',
            ][$featured->status];
            $isLive = in_array($featured->status, ['groups', 'knockout']);
            $progress = $live['total'] ? round($live['played'] / $live['total'] * 100) : 0;
        @endphp

        {{-- Portada del torneo --}}
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-500 via-brand-400 to-brand-300 text-white shadow-xl shadow-brand-200">
            <div class="absolute -top-24 -right-24 size-80 rounded-full bg-ball-300/50"></div>
            <div class="absolute top-16 -right-8 size-80 rounded-full border-[3px] border-white/25"></div>
            <div class="absolute -bottom-24 -left-16 size-72 rounded-full border-[22px] border-white/10"></div>

            <div class="relative px-5 py-7 sm:px-10 sm:py-14">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide uppercase backdrop-blur">
                        @if ($isLive)
                            <span class="relative flex size-2.5">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-ball-300 opacity-75"></span>
                                <span class="relative inline-flex size-2.5 rounded-full bg-ball-300"></span>
                            </span>
                        @endif
                        {{ $statusText }}
                    </span>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                        {{ ucfirst($featured->date->translatedFormat('l j \d\e F')) }} · {{ substr($featured->start_time, 0, 5) }}–{{ substr($featured->end_time, 0, 5) }}
                    </span>
                </div>

                <h1 class="mt-4 max-w-3xl text-3xl leading-tight font-black tracking-tight break-words sm:mt-5 sm:text-6xl sm:leading-none">{{ $featured->name }}</h1>
                @if ($live['organizers']->isNotEmpty())
                    <p class="mt-3 text-sm text-white/80">Organiza{{ $live['organizers']->count() > 1 ? 'n' : '' }}: <span class="font-semibold text-white">{{ $live['organizers']->join(', ', ' y ') }}</span></p>
                @endif

                @if ($live['champion'])
                    <div class="mt-6 flex items-center gap-3 rounded-2xl bg-white/95 px-4 py-3 text-stone-900 shadow-lg sm:mt-8 sm:inline-flex sm:gap-4 sm:px-5 sm:py-4">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-ball-300 text-2xl shadow-inner sm:size-14 sm:text-3xl">🏆</span>
                        <div class="min-w-0">
                            <p class="text-xs font-bold tracking-wide text-brand-600 uppercase">Campeones</p>
                            <p class="text-xl leading-snug font-black break-words sm:text-2xl">{{ $live['champion']->name }}<x-pair-number :pair="$live['champion']" /></p>
                        </div>
                    </div>
                @endif

                {{-- Cifras --}}
                <dl @class(['mt-6 grid grid-cols-2 gap-2 sm:mt-10 sm:gap-3', 'sm:grid-cols-4' => $live['estimate'], 'sm:grid-cols-3' => ! $live['estimate']])>
                    <div class="rounded-2xl bg-white/15 px-3 py-2.5 backdrop-blur sm:px-4 sm:py-3">
                        <dt class="text-xs font-semibold text-white/75">Parejas</dt>
                        <dd class="text-2xl font-black sm:text-3xl">{{ $featured->pairs_count }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/15 px-3 py-2.5 backdrop-blur sm:px-4 sm:py-3">
                        <dt class="text-xs font-semibold text-white/75">{{ $live['groups'] ? 'Grupos' : 'Pistas' }}</dt>
                        <dd class="text-2xl font-black sm:text-3xl">{{ $live['groups'] ?: $featured->courts }}</dd>
                    </div>
                    <div class="rounded-2xl bg-white/15 px-3 py-2.5 backdrop-blur sm:px-4 sm:py-3">
                        <dt class="text-xs font-semibold text-white/75">Partidos jugados</dt>
                        <dd class="text-2xl font-black sm:text-3xl">{{ $live['played'] }}<span class="text-lg font-bold text-white/60">/{{ $live['total'] }}</span></dd>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/25">
                            <div class="h-full rounded-full bg-ball-300" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                    @if ($live['estimate'])
                        <div class="rounded-2xl bg-white/15 px-3 py-2.5 backdrop-blur sm:px-4 sm:py-3">
                            <dt class="text-xs font-semibold text-white/75">{{ $live['estimate']['finished'] ? 'Terminó' : 'Fin estimado' }}</dt>
                            <dd class="text-2xl font-black sm:text-3xl">{{ $live['estimate']['finish']->format('H:i') }}</dd>
                            <p class="text-xs text-white/75">⏱ {{ $live['estimate']['average_minutes'] }} min por partido</p>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        {{-- Accesos (en el móvil ya están en el menú de abajo) --}}
        <nav class="hidden grid-cols-4 gap-3 md:grid">
            @foreach ([
                ['tournaments.groups', '📋', 'Grupos'],
                ['tournaments.bracket', '🏆', 'Cuadro'],
                ['tournaments.matches', '🎾', 'Partidos'],
                ['tournaments.pairs', '👥', 'Parejas'],
            ] as [$route, $icon, $label])
                <a href="{{ route($route, $featured) }}" wire:navigate
                   class="card group flex items-center gap-3 p-4 transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand-50 text-2xl transition group-hover:bg-ball-100">{{ $icon }}</span>
                    <span class="font-bold text-stone-900 group-hover:text-brand-700">{{ $label }}</span>
                </a>
            @endforeach
        </nav>

        @if ($isLive)
            <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
                {{-- En pista --}}
                <section>
                    <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">En pista ahora</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (range(1, $featured->courts) as $court)
                            @php $m = $live['playing']->get($court); @endphp
                            <div class="card overflow-hidden" wire:key="dash-court-{{ $court }}-{{ $m?->id }}">
                                <div class="flex items-center justify-between bg-stone-900 px-4 py-2 text-white">
                                    <span class="font-black tracking-wide">PISTA {{ $court }}</span>
                                    @if ($m)
                                        <span class="text-xs text-white/70">{{ $m->stageLabel() }} · {{ (int) $m->started_at->diffInMinutes(now()) }}′</span>
                                    @elseif ($featured->isCourtClosed($court))
                                        <span class="text-xs text-white/70">Cerrada</span>
                                    @endif
                                </div>
                                <div class="space-y-1 px-4 py-4 text-center sm:py-5">
                                    @if ($m)
                                        <p class="text-lg leading-snug font-bold break-words text-stone-900">{{ $m->pair1->name }}<x-pair-number :pair="$m->pair1" /></p>
                                        <p class="text-xs font-bold text-ball-600">VS</p>
                                        <p class="text-lg leading-snug font-bold break-words text-stone-900">{{ $m->pair2->name }}<x-pair-number :pair="$m->pair2" /></p>
                                    @else
                                        <p class="py-4 text-sm text-stone-400">Sin partido ahora mismo</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($live['nextUp']->isNotEmpty())
                        <h2 class="mt-6 mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Siguientes</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($live['nextUp'] as $next)
                                <div class="card border-ball-300 bg-ball-100 px-4 py-3 text-stone-800" wire:key="dash-next-{{ $next->id }}">
                                    <p class="text-xs font-black tracking-wide text-ball-600 uppercase">{{ $loop->first ? '1.º' : '2.º' }} siguiente</p>
                                    <p class="mt-1 leading-snug font-bold break-words">{{ $next->pair1->name }}<x-pair-number :pair="$next->pair1" /> <span class="font-normal text-stone-400">vs</span></p>
                                    <p class="leading-snug font-bold break-words">{{ $next->pair2->name }}<x-pair-number :pair="$next->pair2" /></p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                {{-- Últimos resultados --}}
                <section>
                    <h2 class="mb-3 text-sm font-bold tracking-wide text-brand-700 uppercase">Últimos resultados</h2>
                    <div class="card divide-y divide-brand-50">
                        @forelse ($live['latest'] as $f)
                            <div class="flex items-center gap-3 px-4 py-2.5" wire:key="dash-res-{{ $f->id }}">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs text-stone-400">{{ $f->stageLabel() }} · {{ $f->finished_at?->format('H:i') }}</p>
                                    <p class="text-sm leading-snug break-words">
                                        <span @class(['font-bold text-stone-900' => $f->winner_id === $f->pair1_id])>{{ $f->pair1->name }}</span>
                                        <span class="text-stone-400">vs</span>
                                        <span @class(['font-bold text-stone-900' => $f->winner_id === $f->pair2_id])>{{ $f->pair2->name }}</span>
                                    </p>
                                </div>
                                <span class="rounded-lg bg-brand-50 px-2 py-1 text-sm font-bold text-brand-700">{{ $f->scoreLabel() }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-stone-500">Aún no hay resultados.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @elseif ($featured->isRegistration())
            <section class="card p-6 text-center">
                <p class="text-4xl">📝</p>
                <p class="mt-2 text-lg font-bold text-stone-900">{{ $featured->pairs_count }} parejas inscritas</p>
            </section>
        @endif

        @if ($others->isNotEmpty())
            <section>
                <h2 class="mb-3 text-sm font-bold tracking-wide text-stone-500 uppercase">Otros torneos</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($others as $t)
                        <a href="{{ route('tournaments.show', $t) }}" class="card p-4 transition hover:border-brand-300" wire:key="other-{{ $t->id }}">
                            <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">{{ $t->date->translatedFormat('j M Y') }}</p>
                            <p class="font-bold text-stone-900">{{ $t->name }}</p>
                            <p class="text-xs text-stone-500">{{ $t->pairs_count }} parejas · {{ $t->statusLabel() }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>

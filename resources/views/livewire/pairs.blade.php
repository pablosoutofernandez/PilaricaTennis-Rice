<div>
    @include('partials.tournament-nav')

    <div @class(['grid gap-6', 'lg:grid-cols-[1fr_400px]' => $canManage])>
        {{-- Parejas --}}
        <section class="space-y-4">
            @if ($canManage && ($tournament->isRegistration() || $tournament->status === 'groups') && ! $pairLimitReached)
                @if ($tournament->status === 'groups')
                    <p class="text-sm text-stone-500"><strong class="text-stone-700">¿Llega una pareja tarde?</strong> Entra en el grupo con menos parejas y sus partidos se reparten por la cola.</p>
                @endif
                <form wire:submit="add" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label class="label" for="p1">Jugador/a 1</label>
                        <input id="p1" wire:model="player1" class="input" placeholder="Nombre" autocomplete="off">
                    </div>
                    <div class="flex-1">
                        <label class="label" for="p2">Jugador/a 2</label>
                        <input id="p2" wire:model="player2" class="input" placeholder="Nombre" autocomplete="off">
                    </div>
                    @if ($tournament->isRegistration())
                        <label class="flex items-center gap-2 pb-2 text-sm text-stone-600" title="Los cabezas de serie van a grupos distintos">
                            <input type="checkbox" wire:model="seeded" class="size-4 accent-brand-500"> Cabeza de serie
                        </label>
                    @endif
                    <button class="btn-primary">{{ $tournament->isRegistration() ? 'Añadir' : 'Añadir al torneo' }}</button>
                </form>
            @endif
            @if ($errors->any())
                <p class="text-sm text-red-600">{{ $errors->first() }}</p>
            @endif
            @if ($canManage && in_array($tournament->status, ['groups', 'knockout']))
                <p class="text-xs text-stone-500">
                    <strong>Sustituir a alguien:</strong> «Editar» cambia los nombres y la pareja conserva sus resultados.
                    <strong>Si una pareja se va:</strong> «Retirar» le da por perdidos (W.O.) el partido que esté jugando y los pendientes, y no pasa a la fase final ni a la consolación.
                </p>
            @endif

            <div class="card">
                <div class="flex items-center justify-between border-b border-brand-50 px-4 py-3">
                    <h2 class="font-bold text-stone-900">Parejas inscritas <span class="text-brand-600">({{ $pairs->count() }}/{{ config('torneo.max_pairs') }})</span></h2>
                    @if ($canManage && $tournament->isRegistration() && ! $pairLimitReached)
                        <div class="flex gap-1">
                            @foreach ([4, 8, 10] as $n)
                                <button wire:click="addSamples({{ $n }})" class="btn-ghost px-2 py-1 text-xs">+{{ $n }} de ejemplo</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($pairLimitReached && $tournament->isRegistration())
                    <p class="border-b border-brand-50 px-4 py-2 text-xs text-stone-500">Se ha alcanzado el máximo de {{ config('torneo.max_pairs') }} parejas.</p>
                @endif
                <ol class="divide-y divide-brand-50">
                    @forelse ($pairs as $i => $pair)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="pair-{{ $pair->id }}">
                            @if ($pair->number)
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ $pair->number }}</span>
                            @endif
                            @if ($editingPairId === $pair->id)
                                <form wire:submit="saveEditing" class="flex flex-1 flex-wrap items-center gap-2">
                                    <input wire:model="editPlayer1" class="input min-w-0 flex-1 py-1.5" aria-label="Jugador/a 1" autocomplete="off">
                                    <input wire:model="editPlayer2" class="input min-w-0 flex-1 py-1.5" aria-label="Jugador/a 2" autocomplete="off">
                                    <button class="btn-primary px-3 py-1.5">Guardar</button>
                                    <button type="button" wire:click="cancelEditing" class="btn-ghost px-3 py-1.5">Cancelar</button>
                                </form>
                            @else
                            <span @class(['flex-1 text-sm font-medium', 'text-stone-400 line-through' => $pair->isWithdrawn()])>{{ $pair->name }}</span>
                            @if ($pair->isWithdrawn())
                                <span class="badge bg-red-50 text-red-700">Retirada {{ $pair->withdrawn_at->format('H:i') }}</span>
                            @endif
                            @if ($pair->group)
                                <span class="badge bg-brand-100 text-brand-700">Grupo {{ $pair->group->name }}</span>
                            @endif
                            @if ($canManage && $tournament->status !== 'finished')
                                <button wire:click="startEditing({{ $pair->id }})" class="btn-ghost px-2 py-1 text-xs" title="Cambiar nombres (sustituciones)">Editar</button>
                            @endif
                            @if (! $canManage)
                            @elseif (in_array($tournament->status, ['groups', 'knockout']) && ! $pair->isWithdrawn())
                                <button wire:click="withdraw({{ $pair->id }})"
                                        wire:confirm="{{ $pair->name }} se retira del torneo: pierde por W.O. el partido que esté jugando y los pendientes.{{ $tournament->status === 'groups' ? ' Se puede deshacer mientras dure la fase de grupos.' : ' No se puede deshacer.' }} ¿Retirar?"
                                        class="btn-ghost px-2 py-1 text-xs text-red-600 hover:bg-red-50">Retirar</button>
                            @elseif ($tournament->status === 'groups' && $pair->isWithdrawn())
                                <button wire:click="reinstate({{ $pair->id }})" class="btn-ghost px-2 py-1 text-xs">Reincorporar</button>
                            @endif
                            @if ($canManage && $tournament->isRegistration())
                                <button wire:click="toggleSeed({{ $pair->id }})" title="Cabeza de serie"
                                        @class(['badge cursor-pointer', 'bg-ball-300 text-stone-800' => $pair->seeded, 'bg-stone-100 text-stone-400 hover:text-stone-600' => ! $pair->seeded])>
                                    ★ {{ $pair->seeded ? 'Cabeza de serie' : '' }}
                                </button>
                                <button wire:click="remove({{ $pair->id }})" class="cursor-pointer text-stone-300 hover:text-red-600" title="Quitar">✕</button>
                            @elseif ($pair->seeded)
                                <span class="badge bg-ball-300 text-stone-800">★</span>
                            @endif
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-stone-500">Añade parejas para ver la propuesta de formato.</li>
                    @endforelse
                </ol>
            </div>
        </section>

        {{-- Formato y ajustes: solo para quien organiza --}}
        @if ($canManage)
        <aside class="space-y-4">
            <div class="card overflow-hidden">
                <div class="bg-gradient-to-br from-brand-400 to-brand-500 px-5 py-4 text-white">
                    <p class="text-xs font-semibold tracking-wide uppercase opacity-80">{{ $tournament->isRegistration() ? 'Formato propuesto' : 'Formato del torneo' }}</p>
                    @if ($proposal)
                        <p class="mt-1 text-xl font-bold">
                            {{ count($proposal['group_sizes']) }} {{ count($proposal['group_sizes']) === 1 ? 'grupo' : 'grupos' }}
                            ({{ implode('-', $proposal['group_sizes']) }}) → {{ \App\Services\FormatPlanner::roundName($proposal['qualifiers']) }}
                        </p>
                    @else
                        <p class="mt-1 text-lg font-bold">Mínimo {{ config('torneo.min_pairs') }} parejas</p>
                    @endif
                </div>
                @if ($proposal)
                    <dl class="grid grid-cols-2 gap-px bg-brand-50 text-sm">
                        <div class="col-span-2 bg-white px-5 py-3">
                            <dt class="label">Clasificación</dt>
                            <dd class="font-medium">{{ $proposal['qualification'] }}</dd>
                        </div>
                        <div class="bg-white px-5 py-3">
                            <dt class="label">Partidos</dt>
                            <dd class="font-medium">{{ $proposal['group_matches'] }} grupos + {{ $proposal['knockout_matches'] }} principal + {{ $proposal['consolation_matches'] }} consolación ({{ $proposal['consolation_pairs'] }} parejas)</dd>
                        </div>
                        <div class="bg-white px-5 py-3">
                            <dt class="label">Mínimo por pareja</dt>
                            <dd class="font-medium">{{ $proposal['min_matches_per_pair'] }} partidos</dd>
                        </div>
                        <div class="bg-white px-5 py-3">
                            <dt class="label">Set desde (propuesto)</dt>
                            <dd>
                                <span class="badge bg-ball-300 text-stone-800">{{ $proposal['start_games'] }}-{{ $proposal['start_games'] }}</span>
                                @if ($proposal['start_games_knockout'] !== $proposal['start_games'])
                                    <span class="text-xs text-stone-500">· cuadros {{ $proposal['start_games_knockout'] }}-{{ $proposal['start_games_knockout'] }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="bg-white px-5 py-3">
                            <dt class="label">Fin estimado</dt>
                            <dd @class(['font-bold', 'text-red-600' => $endsAt > substr($tournament->end_time, 0, 5), 'text-brand-700' => $endsAt <= substr($tournament->end_time, 0, 5)])>
                                {{ $endsAt }} h
                            </dd>
                        </div>
                    </dl>
                    @if ($proposal['warning'])
                        <p class="border-t border-red-100 bg-red-50 px-5 py-2 text-xs text-red-700">{{ $proposal['warning'] }}</p>
                    @endif
                    @if (! $fitsTime)
                        <p class="border-t border-red-100 bg-red-50 px-5 py-2 text-xs text-red-700">Con los marcadores elegidos, el torneo supera el horario disponible.</p>
                    @endif
                @endif
            </div>

            @if ($canManage)
            <div class="card space-y-3 p-5">
                <h3 class="font-bold text-stone-900">Ajustes</h3>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="label" for="st">Inicio</label>
                        <input id="st" type="time" wire:model.blur="start_time" class="input" @disabled(! $tournament->isRegistration())>
                    </div>
                    <div>
                        <label class="label" for="et">Fin</label>
                        <input id="et" type="time" wire:model.blur="end_time" class="input" @disabled($tournament->status === 'finished')>
                    </div>
                    <div>
                        <label class="label" for="ct">Pistas</label>
                        <input id="ct" type="number" min="1" max="{{ config('torneo.max_courts') }}" wire:model.blur="courts" class="input" @disabled($tournament->status === 'finished')>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="sg">Partido de grupos desde</label>
                        <select id="sg" wire:model.live="startGroups" class="input">
                            <option value="">Automático</option>
                            @foreach (range(0, 4) as $g)
                                <option value="{{ $g }}">{{ $g }}-{{ $g }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="sk">Cuadros desde</label>
                        <select id="sk" wire:model.live="startKnockout" class="input">
                            <option value="">Automático</option>
                            @foreach (range(0, 4) as $g)
                                <option value="{{ $g }}">{{ $g }}-{{ $g }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <label for="consolationAll" class="flex cursor-pointer items-start gap-3 rounded-lg border border-brand-100 p-3">
                    <input id="consolationAll" type="checkbox" wire:model.live="consolationAll" class="mt-0.5 size-4 accent-brand-500" @disabled(! $tournament->isRegistration())>
                    <span>
                        <span class="block text-sm font-medium text-stone-800">Incluir a las parejas eliminadas en grupos</span>
                        <span class="mt-0.5 block text-xs text-stone-500">Desmarcado: consolación para quienes pierdan en el cuadro principal.</span>
                        @if ($proposal && $proposal['recommended_consolation_all'] !== null)
                            <span class="mt-1 block text-xs font-medium text-brand-700">
                                ★ Recomendado para {{ $proposal['pairs'] }} parejas: {{ $proposal['recommended_consolation_all'] ? 'marcado' : 'desmarcado' }}
                            </span>
                        @endif
                    </span>
                </label>
                <p class="text-xs text-stone-500">Partidos de un set · duración aproximada: @foreach (config('torneo.match_minutes') as $s => $m){{ $s }}-{{ $s }}: {{ $m }}′{{ $loop->last ? '' : ' · ' }}@endforeach · +{{ config('torneo.changeover_minutes') }}′ de cambio de pista · +{{ config('torneo.organization_margin') * 100 }} % de margen de organización · {{ config('torneo.lunch_break_minutes') }}′ de pistas vacías mientras se turnan para comer si la jornada pasa de {{ intdiv(config('torneo.lunch_break_after_minutes'), 60) }} h</p>
            </div>

            @endif

            @if ($canManage && $tournament->isRegistration())
                <button wire:click="start"
                        wire:confirm="{{ $fitsTime ? '' : 'El fin estimado ('.$endsAt.') se pasa de la hora de fin ('.substr($tournament->end_time, 0, 5).'). ' }}Se sortearán los grupos y empezarán los partidos. ¿Empezar el torneo?"
                        class="btn-ball w-full py-3 text-base" @disabled(! $proposal)>
                    🎾 Sortear grupos y empezar
                </button>
                @error('start') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @endif
        </aside>
        @endif
    </div>
</div>

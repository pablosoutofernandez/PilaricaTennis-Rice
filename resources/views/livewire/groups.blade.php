<div wire:poll.15s>
    @include('partials.tournament-nav')

    @if ($groups->isEmpty())
        <div class="card p-10 text-center text-stone-500">
            <p class="text-4xl">🎾</p>
            <p class="mt-2">Los grupos se sortean al empezar el torneo.</p>
        </div>
    @else
        <div class="mb-4 space-y-2 text-xs text-stone-500">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-brand-200"></span> Clasificado</span>
                <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-ball-300"></span> Clasificado como mejor de su posición</span>
            </div>
            <details>
                <summary class="cursor-pointer font-semibold text-stone-600 select-none">Cómo se desempata</summary>
                <p class="mt-1">Victorias → enfrentamiento directo → diferencia de juegos → juegos a favor → sorteo.</p>
                @can('manage', $tournament)
                    <p>W.O.: cuenta como 6 a marcador inicial +1 (empezando 0-0, 6-1). Pulsa un resultado para corregirlo.</p>
                @endcan
            </details>
        </div>

        <div class="grid gap-4 md:grid-cols-2 md:gap-5 xl:grid-cols-3">
            @foreach ($groups as $item)
                @php $group = $item['group']; @endphp
                <section class="card overflow-hidden" wire:key="group-{{ $group->id }}">
                    <header class="flex items-center justify-between bg-gradient-to-r from-brand-400 to-brand-300 px-4 py-2.5 text-white">
                        <h2 class="text-lg font-bold">Grupo {{ $group->name }}</h2>
                        <span class="text-xs font-semibold opacity-90">
                            {{ $item['matches']->where('status', 'finished')->count() }}/{{ $item['matches']->count() }} partidos
                        </span>
                    </header>

                    <table class="w-full text-sm">
                        <thead class="text-xs text-stone-400">
                            <tr>
                                <th class="w-8 py-2 pl-3 text-left sm:pl-4">#</th>
                                <th class="py-2 pl-1 text-left">Pareja</th>
                                <th class="hidden py-2 text-center sm:table-cell" title="Jugados">PJ</th>
                                <th class="w-9 py-2 text-center" title="Ganados">PG</th>
                                <th class="w-10 py-2 text-center" title="Diferencia de juegos">+/-</th>
                                <th class="hidden py-2 pr-4 text-center sm:table-cell" title="Juegos a favor">JF</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item['standings'] as $row)
                                @php
                                    $isIn = in_array($row['pair']->id, $qualified);
                                    $byPosition = $isIn && $row['position'] <= $perGroup;
                                @endphp
                                <tr @class([
                                        'border-t border-brand-50',
                                        'bg-brand-50' => $byPosition,
                                        'bg-ball-100' => $isIn && ! $byPosition,
                                    ])>
                                    <td class="py-2 pl-3 align-top sm:pl-4">
                                        <span @class([
                                            'grid size-6 place-items-center rounded-full text-xs font-bold',
                                            'bg-brand-200 text-brand-800' => $byPosition,
                                            'bg-ball-300 text-stone-800' => $isIn && ! $byPosition,
                                            'bg-stone-100 text-stone-400' => ! $isIn,
                                        ])>{{ $row['position'] }}</span>
                                    </td>
                                    <td class="py-2 pl-1 leading-snug font-medium break-words">
                                        <span @class(['text-stone-400 line-through' => $row['withdrawn']])>{{ $row['pair']->name }}</span><x-pair-number :pair="$row['pair']" />
                                        @if ($row['withdrawn'])
                                            <span class="badge ml-1 bg-red-50 text-red-700">Retirada</span>
                                        @endif
                                    </td>
                                    <td class="hidden py-2 text-center text-stone-500 sm:table-cell">{{ $row['played'] }}</td>
                                    <td class="py-2 text-center font-bold text-brand-700">{{ $row['won'] }}</td>
                                    <td class="py-2 text-center text-stone-500">{{ $row['diff'] > 0 ? '+' : '' }}{{ $row['diff'] }}</td>
                                    <td class="hidden py-2 pr-4 text-center text-stone-500 sm:table-cell">{{ $row['gf'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <ul class="divide-y divide-brand-50 border-t border-brand-100 bg-brand-50/30 text-xs">
                        @foreach ($item['matches'] as $m)
                            @if ($editing === $m->id)
                                <li>@include('partials.result-form')</li>
                                @continue
                            @endif
                            <li class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-1.5" wire:key="gm-{{ $m->id }}">
                                <span @class(['min-w-0 flex-1 text-right leading-snug break-words', 'font-bold text-stone-900' => $m->winner_id === $m->pair1_id, 'text-stone-500' => $m->winner_id !== $m->pair1_id])>{{ $m->pair1->name }}<x-pair-number :pair="$m->pair1" /></span>
                                @if ($m->status === 'finished')
                                    @can('manage', $tournament)
                                        <button type="button" wire:click="edit({{ $m->id }})"
                                                class="w-12 shrink-0 cursor-pointer rounded bg-white px-1 py-1 text-center font-bold text-brand-700 ring-1 ring-brand-100 hover:ring-brand-400"
                                                title="{{ $m->walkover ? 'Sin jugar: cuenta '.$m->games1.'-'.$m->games2.'. ' : '' }}Pulsa para corregir">{{ $m->scoreLabel() }}</button>
                                    @else
                                        <span class="w-12 shrink-0 rounded bg-white px-1 py-0.5 text-center font-bold text-brand-700 ring-1 ring-brand-100"
                                              @if ($m->walkover) title="Sin jugar: cuenta {{ $m->games1 }}-{{ $m->games2 }}" @endif>{{ $m->scoreLabel() }}</span>
                                    @endcan
                                @elseif ($m->status === 'playing')
                                    <span class="w-12 shrink-0 animate-pulse rounded bg-ball-300 px-1 py-0.5 text-center font-bold text-stone-800">P{{ $m->court }}</span>
                                @else
                                    <span class="w-12 shrink-0 text-center text-stone-300">vs</span>
                                @endif
                                <span @class(['min-w-0 flex-1 leading-snug break-words', 'font-bold text-stone-900' => $m->winner_id === $m->pair2_id, 'text-stone-500' => $m->winner_id !== $m->pair2_id])>{{ $m->pair2->name }}<x-pair-number :pair="$m->pair2" /></span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>

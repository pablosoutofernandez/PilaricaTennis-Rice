<div wire:poll.15s>
    @include('partials.tournament-nav')

    @if ($groups->isEmpty())
        <div class="card p-10 text-center text-stone-500">
            <p class="text-4xl">🎾</p>
            <p class="mt-2">Los grupos se sortean al empezar el torneo.</p>
        </div>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-3 text-xs text-stone-500">
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-brand-200"></span> Clasificado por posición</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-ball-300"></span> Clasificado como mejor de su posición</span>
            <span>Desempate: victorias → enfrentamiento directo (2 empatados) → diferencia de juegos → juegos a favor</span>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
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
                                <th class="w-8 py-2 pl-4 text-left">#</th>
                                <th class="py-2 text-left">Pareja</th>
                                <th class="py-2 text-center" title="Jugados">PJ</th>
                                <th class="py-2 text-center" title="Ganados">PG</th>
                                <th class="py-2 text-center" title="Diferencia de juegos">+/-</th>
                                <th class="py-2 pr-4 text-center" title="Juegos a favor">JF</th>
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
                                    <td class="py-2 pl-4">
                                        <span @class([
                                            'grid size-6 place-items-center rounded-full text-xs font-bold',
                                            'bg-brand-200 text-brand-800' => $byPosition,
                                            'bg-ball-300 text-stone-800' => $isIn && ! $byPosition,
                                            'bg-stone-100 text-stone-400' => ! $isIn,
                                        ])>{{ $row['position'] }}</span>
                                    </td>
                                    <td class="py-2 font-medium">{{ $row['pair']->name }}</td>
                                    <td class="py-2 text-center text-stone-500">{{ $row['played'] }}</td>
                                    <td class="py-2 text-center font-bold text-brand-700">{{ $row['won'] }}</td>
                                    <td class="py-2 text-center text-stone-500">{{ $row['diff'] > 0 ? '+' : '' }}{{ $row['diff'] }}</td>
                                    <td class="py-2 pr-4 text-center text-stone-500">{{ $row['gf'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <ul class="divide-y divide-brand-50 border-t border-brand-100 bg-brand-50/30 text-xs">
                        @foreach ($item['matches'] as $m)
                            <li class="flex items-center gap-2 px-4 py-1.5" wire:key="gm-{{ $m->id }}">
                                <span @class(['flex-1 truncate text-right', 'font-bold text-stone-900' => $m->winner_id === $m->pair1_id, 'text-stone-500' => $m->winner_id !== $m->pair1_id])>{{ $m->pair1->name }}</span>
                                @if ($m->status === 'finished')
                                    <span class="w-12 rounded bg-white px-1 text-center font-bold text-brand-700 ring-1 ring-brand-100">{{ $m->games1 }}-{{ $m->games2 }}</span>
                                @elseif ($m->status === 'playing')
                                    <span class="w-12 animate-pulse rounded bg-ball-300 px-1 text-center font-bold text-stone-800">P{{ $m->court }}</span>
                                @else
                                    <span class="w-12 text-center text-stone-300">vs</span>
                                @endif
                                <span @class(['flex-1 truncate', 'font-bold text-stone-900' => $m->winner_id === $m->pair2_id, 'text-stone-500' => $m->winner_id !== $m->pair2_id])>{{ $m->pair2->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endif
</div>

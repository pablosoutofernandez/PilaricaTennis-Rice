<div>
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Formatos según el número de parejas</h1>
            <p class="mt-1 max-w-2xl text-sm text-stone-500">
                Grupos equilibrados y cuadros sin byes, buscando que todas las parejas jueguen lo máximo posible.
                Los tiempos incluyen {{ $changeover }} min de cambio de pista entre partidos, un {{ config('torneo.organization_margin') * 100 }} % de margen de organización
                y {{ config('torneo.lunch_break_minutes') }} min de pistas vacías a mediodía (se juega mientras la gente se turna para comer) en jornadas de más de {{ intdiv(config('torneo.lunch_break_after_minutes'), 60) }} h.
                <span class="font-medium text-stone-700">★</span> marca el modo de consolación recomendado.
            </p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="hours">Horas disponibles</label>
                <input id="hours" type="number" min="1" max="16" wire:model.live.debounce.300ms="hours" class="input w-28">
            </div>
            <div>
                <label class="label" for="courts">Pistas</label>
                <input id="courts" type="number" min="1" max="2" wire:model.live.debounce.300ms="courts" class="input w-24">
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm text-stone-700">
                <input type="checkbox" wire:model.live="consolationAll" class="size-4 accent-brand-500">
                Incluir a las parejas eliminadas en grupos
            </label>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-xs">
        @foreach ($durations as $start => $min)
            <span class="badge bg-white text-stone-600 ring-1 ring-brand-100">Empezando {{ $start }}-{{ $start }} ≈ {{ $min }} min/partido + {{ $changeover }} de cambio</span>
        @endforeach
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-left text-xs tracking-wide text-brand-800 uppercase">
                <tr>
                    <th class="px-4 py-3">Parejas</th>
                    <th class="px-4 py-3">Grupos</th>
                    <th class="px-4 py-3">Clasificación</th>
                    <th class="px-4 py-3">Cuadro</th>
                    <th class="px-4 py-3">Consolación</th>
                    <th class="px-4 py-3">Cómo funciona la consolación</th>
                    <th class="px-4 py-3 text-center">Set desde</th>
                    <th class="px-4 py-3 text-center">Partidos</th>
                    <th class="px-4 py-3 text-center">Mín. por pareja</th>
                    <th class="px-4 py-3 text-center">Duración</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @foreach ($rows as $r)
                    <tr class="hover:bg-brand-50/50" wire:key="row-{{ $r['pairs'] }}">
                        <td class="px-4 py-2.5 font-bold text-brand-700">{{ $r['pairs'] }}</td>
                        <td class="px-4 py-2.5">
                            {{ count($r['group_sizes']) }} {{ count($r['group_sizes']) === 1 ? 'grupo' : 'grupos' }}
                            <span class="text-stone-400">({{ implode('-', $r['group_sizes']) }})</span>
                        </td>
                        <td class="px-4 py-2.5">{{ $r['qualification'] }}</td>
                        <td class="px-4 py-2.5">{{ \App\Services\FormatPlanner::roundName($r['qualifiers']) }}</td>
                        <td class="px-4 py-2.5">
                            {{ $r['consolation_pairs'] }} parejas · {{ $r['consolation_matches'] }} partidos
                            @if ($r['recommended_consolation_all'] === $consolationAll)
                                <span class="badge ml-1 bg-ball-300 text-stone-800" title="Modo de consolación recomendado">★ Recomendado</span>
                            @elseif ($r['recommended_consolation_all'] !== null)
                                <span class="block text-xs text-stone-400">★ Mejor {{ $r['recommended_consolation_all'] ? 'incluyendo a las eliminadas en grupos' : 'solo para el cuadro principal' }}</span>
                            @endif
                        </td>
                        <td class="min-w-64 px-4 py-2.5 text-xs text-stone-600">{{ $r['consolation_description'] }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span @class(['badge', 'bg-ball-300 text-stone-800' => $r['start_games'] > 0, 'bg-brand-100 text-brand-700' => $r['start_games'] === 0])>
                                {{ $r['start_games'] }}-{{ $r['start_games'] }}
                            </span>
                            @if ($r['start_games_knockout'] !== $r['start_games'])
                                <span class="block text-xs text-stone-500">final {{ $r['start_games_knockout'] }}-{{ $r['start_games_knockout'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-center">{{ $r['group_matches'] }} + {{ $r['knockout_matches'] }} + {{ $r['consolation_matches'] }}</td>
                        <td class="px-4 py-2.5 text-center">{{ $r['min_matches_per_pair'] }}</td>
                        <td class="px-4 py-2.5 text-center">
                            {{ intdiv($r['estimated_minutes'], 60) }}h {{ str_pad($r['estimated_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}
                            @if ($r['warning'])
                                <span class="block text-xs text-red-600">{{ $r['warning'] }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@php
    $statusColors = [
        'registration' => 'bg-stone-100 text-stone-600',
        'groups' => 'bg-brand-100 text-brand-700',
        'knockout' => 'bg-ball-300 text-stone-800',
        'finished' => 'bg-emerald-100 text-emerald-700',
    ];
@endphp
<div class="mb-5 md:mb-6">
    <div>
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <h1 class="text-xl leading-tight font-bold text-stone-900 md:text-2xl">{{ $tournament->name }}</h1>
            <span class="badge {{ $statusColors[$tournament->status] }}">{{ $tournament->statusLabel() }}</span>
        </div>
        <p class="mt-0.5 text-sm text-stone-500">
            {{ ucfirst($tournament->date->translatedFormat('l j \d\e F')) }} ·
            {{ substr($tournament->start_time, 0, 5) }}–{{ substr($tournament->end_time, 0, 5) }}<span class="hidden sm:inline"> ·
            {{ $tournament->courts }} pistas</span>
        </p>
        @if (auth()->user()?->can('manage', $tournament) && ($estimate = $tournament->finishEstimate()))
            @php $delay = $estimate['delay_minutes']; @endphp
            <p @class([
                    'mt-2 flex flex-wrap items-center gap-x-1.5 rounded-xl px-3 py-1.5 text-sm sm:inline-flex sm:py-1',
                    'bg-emerald-50 text-emerald-800' => $estimate['finished'] || $delay <= 0,
                    'bg-ball-100 text-stone-800' => ! $estimate['finished'] && $delay > 0 && $delay <= 15,
                    'bg-red-50 text-red-700' => ! $estimate['finished'] && $delay > 15,
               ])
               title="Fin previsto en el horario: {{ $estimate['scheduled_finish']->format('H:i') }}. Se recalcula con los partidos que quedan y lo que están durando de verdad.">
                @if ($estimate['finished'])
                    🏆 Terminó a las <strong>{{ $estimate['finish']->format('H:i') }}</strong>
                @elseif ($estimate['paused'])
                    ⏸ Todas las pistas cerradas · si se reabren ahora, fin estimado <strong>{{ $estimate['finish']->format('H:i') }}</strong>
                @else
                    🏁 Fin estimado <strong>{{ $estimate['finish']->format('H:i') }}</strong>
                @endif
                <span class="opacity-75">
                    <span class="hidden sm:inline">·</span> ⏱ {{ $estimate['average_is_real'] ? 'media' : 'media prevista' }} <strong>{{ $estimate['average_minutes'] }} min</strong> por partido
                </span>
            </p>
        @endif
    </div>
</div>

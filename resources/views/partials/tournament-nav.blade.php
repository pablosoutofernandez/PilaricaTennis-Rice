@php
    $tabs = [
        'tournaments.pairs' => 'Parejas',
        'tournaments.groups' => 'Grupos',
        'tournaments.bracket' => 'Cuadro',
        'tournaments.matches' => 'Partidos',
    ];
    $statusColors = [
        'registration' => 'bg-stone-100 text-stone-600',
        'groups' => 'bg-brand-100 text-brand-700',
        'knockout' => 'bg-ball-300 text-stone-800',
        'finished' => 'bg-emerald-100 text-emerald-700',
    ];
@endphp
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-stone-900">{{ $tournament->name }}</h1>
            <span class="badge {{ $statusColors[$tournament->status] }}">{{ $tournament->statusLabel() }}</span>
        </div>
        <p class="text-sm text-stone-500">
            {{ $tournament->date->translatedFormat('l j \d\e F') }} ·
            {{ substr($tournament->start_time, 0, 5) }}–{{ substr($tournament->end_time, 0, 5) }} ·
            {{ $tournament->courts }} pistas
        </p>
    </div>
    <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-brand-100/70 p-1">
        @foreach ($tabs as $route => $label)
            <a href="{{ route($route, $tournament) }}" wire:navigate
               @class([
                   'whitespace-nowrap rounded-xl px-4 py-1.5 text-sm font-semibold transition',
                   'bg-white text-brand-700 shadow-sm' => request()->routeIs($route),
                   'text-brand-800/70 hover:text-brand-800' => ! request()->routeIs($route),
               ])>{{ $label }}</a>
        @endforeach
    </nav>
</div>

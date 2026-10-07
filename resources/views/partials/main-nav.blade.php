{{-- Menú principal: siempre las mismas pestañas en el mismo sitio --}}
@php
    $links = [['home', null, 'Inicio']];
    if ($navTournament) {
        $links[] = ['tournaments.groups', $navTournament, 'Grupos'];
        $links[] = ['tournaments.bracket', $navTournament, 'Cuadro'];
        $links[] = ['tournaments.matches', $navTournament, 'Partidos'];
        $links[] = ['tournaments.pairs', $navTournament, 'Parejas'];
    }
    if (auth()->user()?->can('admin')) {
        $links[] = ['admin.tournaments', null, 'Admin'];
    }
@endphp
@foreach ($links as [$route, $parameter, $label])
    @php $active = $route === 'admin.tournaments' ? request()->routeIs('admin.*', 'formats') : request()->routeIs($route); @endphp
    <a href="{{ route($route, $parameter) }}" wire:navigate
       @class([
           'whitespace-nowrap rounded-xl px-3.5 py-1.5 text-sm font-semibold transition',
           'bg-brand-100 text-brand-800' => $active,
           'text-stone-500 hover:bg-brand-50 hover:text-brand-800' => ! $active,
       ])>{{ $label }}</a>
@endforeach

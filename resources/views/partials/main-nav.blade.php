{{--
    Menú principal: siempre las mismas pestañas en el mismo sitio.
    $variant: 'top' (cabecera en pantallas grandes) | 'bottom' (barra inferior en el móvil)
--}}
@php
    $links = [['home', null, 'Inicio', '🏠']];
    if ($navTournament) {
        $links[] = ['tournaments.groups', $navTournament, 'Grupos', '📋'];
        $links[] = ['tournaments.bracket', $navTournament, 'Cuadro', '🏆'];
        $links[] = ['tournaments.matches', $navTournament, 'Partidos', '🎾'];
        $links[] = ['tournaments.pairs', $navTournament, 'Parejas', '👥'];
    }
    // En el móvil, Admin va en la cabecera para que la barra inferior quepa entera.
    if ($variant === 'top' && auth()->user()?->can('admin')) {
        $links[] = ['admin.tournaments', null, 'Admin', '⚙️'];
    }
@endphp
@foreach ($links as [$route, $parameter, $label, $icon])
    @php $active = $route === 'admin.tournaments' ? request()->routeIs('admin.*', 'formats') : request()->routeIs($route); @endphp
    @if ($variant === 'bottom')
        <a href="{{ route($route, $parameter) }}" wire:navigate @if ($active) aria-current="page" @endif
           @class([
               'relative flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-semibold',
               'text-brand-700' => $active,
               'text-stone-500' => ! $active,
           ])>
            @if ($active)
                <span class="absolute inset-x-4 top-0 h-0.5 rounded-full bg-brand-500"></span>
            @endif
            <span aria-hidden="true" class="text-lg leading-none">{{ $icon }}</span>
            {{ $label }}
        </a>
    @else
        <a href="{{ route($route, $parameter) }}" wire:navigate @if ($active) aria-current="page" @endif
           @class([
               'whitespace-nowrap rounded-xl px-3.5 py-1.5 text-sm font-semibold transition',
               'bg-brand-100 text-brand-800' => $active,
               'text-stone-500 hover:bg-brand-50 hover:text-brand-800' => ! $active,
           ])>{{ $label }}</a>
    @endif
@endforeach

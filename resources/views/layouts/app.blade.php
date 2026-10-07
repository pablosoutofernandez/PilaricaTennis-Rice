<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f9822b">
    <title>{{ $title ?? 'Torneo de Tenis' }} · Dobles en un día</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎾</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    // Las pestañas del menú van al torneo que se está viendo o, fuera de él, al destacado.
    $routeTournament = request()->route('tournament');
    $navTournament = $routeTournament instanceof \App\Models\Tournament
        ? $routeTournament
        : ($routeTournament ? \App\Models\Tournament::find($routeTournament) : \App\Models\Tournament::featured());
@endphp
<body class="min-h-screen bg-gradient-to-b from-brand-50 via-white to-white font-sans text-stone-800 antialiased">
    <header class="sticky top-0 z-20 border-b border-brand-100 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 md:h-16 md:gap-4">
            <a href="{{ route('home') }}" wire:navigate class="flex min-w-0 shrink-0 items-center gap-2">
                <span class="grid size-8 place-items-center rounded-full bg-ball-300 text-base shadow-inner ring-2 ring-white md:size-9 md:text-lg">🎾</span>
                <span class="truncate text-sm font-bold text-brand-700 md:text-base">Torneo de Dobles</span>
            </a>

            <nav class="hidden flex-1 items-center justify-center gap-1 md:flex">
                @include('partials.main-nav', ['variant' => 'top'])
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-1 md:ml-0">
                @can('admin')
                    <a href="{{ route('admin.tournaments') }}" wire:navigate class="btn-ghost px-2.5 text-xs md:hidden">Admin</a>
                @endcan
                @guest
                    <a href="{{ route('login') }}" wire:navigate class="btn-ghost px-2.5 text-xs text-stone-500">Acceso organización</a>
                @endguest
                @auth
                    <span class="hidden px-2 text-xs text-stone-400 lg:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-ghost px-2.5 text-xs text-stone-500">Salir</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main @class(['mx-auto max-w-7xl px-4 py-5 md:py-6', 'pb-28 md:pb-6' => $navTournament])>
        @unless (request()->routeIs('home'))
            <a href="{{ route('home') }}" wire:navigate aria-label="Volver al inicio"
               class="mb-4 inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-5 text-sm font-bold text-brand-700 shadow-sm ring-1 ring-brand-200 transition hover:bg-brand-50 hover:ring-brand-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 md:mb-5">
                <span aria-hidden="true" class="text-base">←</span> Volver al inicio
            </a>
        @endunless

        {{ $slot }}
    </main>

    {{-- En el móvil, el menú abajo, a mano del pulgar --}}
    @if ($navTournament)
        <nav aria-label="Menú" class="fixed inset-x-0 bottom-0 z-30 border-t border-brand-100 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur md:hidden">
            <div class="mx-auto grid max-w-md grid-cols-5">
                @include('partials.main-nav', ['variant' => 'bottom'])
            </div>
        </nav>
    @endif
</body>
</html>

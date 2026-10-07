<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <header class="sticky top-0 z-20 border-b border-brand-100 bg-white/85 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4">
            <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-full bg-ball-300 text-lg shadow-inner ring-2 ring-white">🎾</span>
                <span class="text-base font-bold text-brand-700">Torneo de Dobles</span>
            </a>

            <nav class="hidden flex-1 items-center justify-center gap-1 md:flex">
                @include('partials.main-nav')
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-1 md:ml-0">
                @guest
                    <a href="{{ route('login') }}" wire:navigate class="btn-ghost text-xs text-stone-500">Acceso organización</a>
                @endguest
                @auth
                    <span class="hidden px-2 text-xs text-stone-400 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-ghost text-xs text-stone-500">Salir</button>
                    </form>
                @endauth
            </div>
        </div>

        {{-- En el móvil, las pestañas en su propia fila --}}
        <nav class="flex gap-1 overflow-x-auto border-t border-brand-50 px-4 py-2 md:hidden">
            @include('partials.main-nav')
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @unless (request()->routeIs('home'))
            <a href="{{ route('home') }}" wire:navigate aria-label="Volver al inicio"
               class="mb-5 inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-5 text-sm font-bold text-brand-700 shadow-sm ring-1 ring-brand-200 transition hover:bg-brand-50 hover:ring-brand-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500">
                <span aria-hidden="true" class="text-base">←</span> Volver al inicio
            </a>
        @endunless

        {{ $slot }}
    </main>
</body>
</html>

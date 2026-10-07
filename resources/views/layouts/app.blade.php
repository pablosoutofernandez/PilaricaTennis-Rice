<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Torneo de Tenis' }} · Dobles en un día</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎾</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-b from-brand-50 via-white to-white font-sans text-stone-800 antialiased">
    <header class="border-b border-brand-100 bg-white/80 backdrop-blur sticky top-0 z-20">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-full bg-ball-300 text-lg shadow-inner ring-2 ring-white">🎾</span>
                <span class="leading-tight">
                    <span class="block text-base font-bold text-brand-700">Torneo de Dobles</span>
                    <span class="block text-xs text-stone-500">Gestión de torneo en un día</span>
                </span>
            </a>
            <nav class="flex items-center gap-1 text-sm">
                <a href="{{ route('home') }}" wire:navigate class="btn-ghost">Torneos</a>
                <a href="{{ route('formats') }}" wire:navigate class="btn-ghost">Formatos</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="py-8 text-center text-xs text-stone-400">
        Sets a 1 set · 2 pistas · fase de grupos + fase final
    </footer>
</body>
</html>

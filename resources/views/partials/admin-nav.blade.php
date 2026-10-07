@php
    $adminTabs = [
        'admin.tournaments' => 'Torneos',
        'formats' => 'Formatos',
        'admin.users' => 'Usuarios',
    ];
@endphp
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <h1 class="text-2xl font-bold text-stone-900">Administración</h1>
    <nav class="flex gap-1 overflow-x-auto rounded-2xl bg-stone-100 p-1">
        @foreach ($adminTabs as $route => $label)
            <a href="{{ route($route) }}" wire:navigate
               @class([
                   'whitespace-nowrap rounded-xl px-4 py-1.5 text-sm font-semibold transition',
                   'bg-white text-stone-900 shadow-sm' => request()->routeIs($route),
                   'text-stone-500 hover:text-stone-800' => ! request()->routeIs($route),
               ])>{{ $label }}</a>
        @endforeach
    </nav>
</div>

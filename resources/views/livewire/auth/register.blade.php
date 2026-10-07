<div class="mx-auto max-w-sm py-10">
    <div class="card p-6">
        <h1 class="text-xl font-bold text-stone-900">Pedir acceso de organización</h1>
        <p class="mt-1 text-sm text-stone-500">Crea tu usuario y el administrador te dará permiso en el torneo que vayas a organizar.</p>

        <form wire:submit="register" class="mt-5 space-y-3">
            <div>
                <label class="label" for="name">Usuario</label>
                <input id="name" wire:model="name" class="input" autocomplete="username" autofocus>
            </div>
            <div>
                <label class="label" for="password">Contraseña</label>
                <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password">
            </div>
            <div>
                <label class="label" for="password_confirmation">Repite la contraseña</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="input" autocomplete="new-password">
            </div>
            @if ($errors->any())
                <p class="text-sm text-red-600">{{ $errors->first() }}</p>
            @endif
            <button class="btn-primary w-full">Crear usuario</button>
        </form>

        <p class="mt-5 text-center text-xs text-stone-400">
            ¿Ya tienes usuario? <a href="{{ route('login') }}" wire:navigate class="text-brand-700 underline">Entra</a>
        </p>
    </div>
</div>

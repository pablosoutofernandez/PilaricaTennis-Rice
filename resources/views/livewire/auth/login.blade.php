<div class="mx-auto max-w-sm py-10">
    <div class="card p-6">
        <h1 class="text-xl font-bold text-stone-900">Acceso de organización</h1>

        <form wire:submit="login" class="mt-5 space-y-3">
            <div>
                <label class="label" for="name">Usuario</label>
                <input id="name" wire:model="name" class="input" autocomplete="username" autofocus>
            </div>
            <div>
                <label class="label" for="password">Contraseña</label>
                <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password">
            </div>
            @if ($errors->any())
                <p class="text-sm text-red-600">{{ $errors->first() }}</p>
            @endif
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="remember" class="size-4 accent-brand-500"> Mantener la sesión
            </label>
            <button class="btn-primary w-full">Entrar</button>
        </form>

        <p class="mt-5 text-center text-xs text-stone-400">
            ¿Vas a organizar y no tienes cuenta?
            <a href="{{ route('register') }}" wire:navigate class="text-brand-700 underline">Pídela aquí</a>
        </p>
    </div>
</div>

<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Acceso')]
class Login extends Component
{
    /** Intentos fallidos permitidos por usuario e IP antes de esperar un minuto. */
    private const MAX_ATTEMPTS = 5;

    #[Validate('required|string', as: 'usuario')]
    public string $name = '';

    #[Validate('required|string', as: 'contraseña')]
    public string $password = '';

    public bool $remember = true;

    public function login()
    {
        $this->validate();

        $throttleKey = Str::lower($this->name).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'name' => 'Demasiados intentos. Prueba de nuevo en '.RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        if (! Auth::attempt(['name' => $this->name, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['name' => 'Usuario o contraseña incorrectos.']);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        return $this->redirectIntended(route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}

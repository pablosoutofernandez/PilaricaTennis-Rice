<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Alta de quien quiere organizar. Entra sin permisos: el administrador decide en qué
 * torneos puede organizar.
 */
#[Title('Pedir acceso')]
class Register extends Component
{
    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:60|unique:users,name',
            'password' => 'required|string|min:6|confirmed',
        ], attributes: ['name' => 'usuario', 'password' => 'contraseña']);

        $user = User::create($validated);

        Auth::login($user);
        session()->regenerate();
        session()->flash('status', 'Cuenta creada. El administrador tiene que darte acceso a un torneo para que puedas organizarlo.');

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Tournament;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Component;

/** El administrador decide quién organiza cada torneo. */
#[Title('Usuarios')]
class Users extends Component
{
    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function toggleOrganizer(int $userId, int $tournamentId): void
    {
        $this->authorize('admin');

        $user = User::where('is_admin', false)->findOrFail($userId);
        $user->tournaments()->toggle(Tournament::findOrFail($tournamentId)->id);
    }

    public function deleteUser(int $userId): void
    {
        $this->authorize('admin');

        User::where('is_admin', false)->findOrFail($userId)->delete();
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::where('is_admin', false)->with('tournaments:id')->orderBy('name')->get(),
            'tournaments' => Tournament::latest('date')->get(),
        ]);
    }
}

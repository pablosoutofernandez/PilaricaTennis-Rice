<?php

namespace App\Livewire;

use App\Models\Tournament;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Torneos')]
class TournamentIndex extends Component
{
    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|date_format:H:i')]
    public string $start_time = '09:00';

    #[Validate('required|date_format:H:i|after:start_time')]
    public string $end_time = '20:00';

    #[Validate('required|integer|min:1|max:2')]
    public int $courts = 2;

    public function mount(): void
    {
        $this->date = now()->next('Saturday')->toDateString();
    }

    public function create()
    {
        $tournament = Tournament::create($this->validate());

        return $this->redirectRoute('tournaments.pairs', $tournament, navigate: true);
    }

    public function delete(Tournament $tournament): void
    {
        $tournament->delete();
    }

    public function render()
    {
        return view('livewire.tournament-index', [
            'tournaments' => Tournament::withCount('pairs')->latest('date')->get(),
        ]);
    }
}

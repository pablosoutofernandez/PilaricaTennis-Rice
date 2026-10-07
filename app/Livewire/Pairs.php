<?php

namespace App\Livewire;

use App\Models\Pair;
use App\Models\Tournament;
use App\Services\FormatPlanner;
use App\Services\TournamentManager;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Pairs extends Component
{
    public Tournament $tournament;

    #[Validate('required|string|max:60', as: 'jugador 1')]
    public string $player1 = '';

    #[Validate('required|string|max:60', as: 'jugador 2')]
    public string $player2 = '';

    public bool $seeded = false;

    // Ajustes del torneo ('' = automático)
    public string $start_time = '';

    public string $end_time = '';

    public int $courts = 2;

    public string $startGroups = '';

    public string $startKnockout = '';

    public bool $consolationAll = false;

    public function mount(Tournament $tournament): void
    {
        $this->tournament = $tournament;
        $this->start_time = substr($tournament->start_time, 0, 5);
        $this->end_time = substr($tournament->end_time, 0, 5);
        $this->courts = $tournament->courts;
        $this->startGroups = (string) ($tournament->start_games_groups ?? '');
        $this->startKnockout = (string) ($tournament->start_games_knockout ?? '');
        $this->consolationAll = $tournament->consolation_all;
    }

    public function add(): void
    {
        abort_unless($this->tournament->isRegistration(), 403);

        if ($this->tournament->pairs()->count() >= config('torneo.max_pairs')) {
            throw ValidationException::withMessages([
                'player1' => 'El máximo es de '.config('torneo.max_pairs').' parejas.',
            ]);
        }

        $this->validate();
        $this->tournament->pairs()->create([
            'player1' => trim($this->player1),
            'player2' => trim($this->player2),
            'seeded' => $this->seeded,
        ]);

        $this->reset('player1', 'player2', 'seeded');
    }

    public function remove(Pair $pair): void
    {
        abort_unless($this->tournament->isRegistration() && $pair->tournament_id === $this->tournament->id, 403);
        $pair->delete();
    }

    public function toggleSeed(Pair $pair): void
    {
        abort_unless($this->tournament->isRegistration() && $pair->tournament_id === $this->tournament->id, 403);
        $pair->update(['seeded' => ! $pair->seeded]);
    }

    /** Rellena con parejas de ejemplo para probar el torneo. */
    public function addSamples(int $count = 10): void
    {
        abort_unless($this->tournament->isRegistration(), 403);

        $count = min($count, max(0, config('torneo.max_pairs') - $this->tournament->pairs()->count()));
        if ($count === 0) {
            return;
        }

        $names = ['Ana', 'Luis', 'Marta', 'Pablo', 'Lucía', 'Javi', 'Sara', 'Dani', 'Elena', 'Hugo', 'Carmen', 'Álex',
            'Paula', 'Iván', 'Noa', 'Raúl', 'Irene', 'Sergio', 'Clara', 'Diego', 'Nerea', 'Mario', 'Alba', 'Óscar',
            'Laura', 'Rubén', 'Julia', 'Adrián', 'Eva', 'Marcos', 'Inés', 'Víctor'];
        $surnames = ['García', 'López', 'Martín', 'Sánchez', 'Pérez', 'Gómez', 'Ruiz', 'Díaz', 'Moreno', 'Álvarez',
            'Romero', 'Navarro', 'Torres', 'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Castro', 'Rey', 'Otero'];

        for ($i = 0; $i < $count; $i++) {
            $this->tournament->pairs()->create([
                'player1' => fake()->randomElement($names).' '.fake()->randomElement($surnames),
                'player2' => fake()->randomElement($names).' '.fake()->randomElement($surnames),
            ]);
        }
    }

    public function saveSettings(): void
    {
        $this->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'courts' => 'required|integer|min:1|max:2',
            'startGroups' => 'nullable|in:0,1,2,3,4',
            'startKnockout' => 'nullable|in:0,1,2,3,4',
            'consolationAll' => 'boolean',
        ]);

        $this->tournament->update([
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'courts' => $this->courts,
            'start_games_groups' => $this->startGroups === '' ? null : (int) $this->startGroups,
            'start_games_knockout' => $this->startKnockout === '' ? null : (int) $this->startKnockout,
            'consolation_all' => $this->consolationAll,
        ]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['start_time', 'end_time', 'courts', 'startGroups', 'startKnockout', 'consolationAll'])) {
            $this->saveSettings();
        }
    }

    public function start(TournamentManager $manager)
    {
        $manager->start($this->tournament);

        return $this->redirectRoute('tournaments.matches', $this->tournament, navigate: true);
    }

    public function render(FormatPlanner $planner)
    {
        $proposal = $this->tournament->proposal();
        $estimate = null;
        $pairLimitReached = $this->tournament->pairs()->count() >= config('torneo.max_pairs');

        if ($proposal) {
            $estimate = $planner->estimate(
                $proposal,
                $this->tournament->start_games_groups ?? $proposal['start_games'],
                $this->tournament->start_games_knockout ?? $proposal['start_games_knockout'],
            );
        }
        $fitsTime = $estimate !== null && $estimate <= $this->tournament->availableMinutes();

        return view('livewire.pairs', [
            'pairs' => $this->tournament->pairs()->with('group')->orderBy('id')->get(),
            'proposal' => $proposal,
            'pairLimitReached' => $pairLimitReached,
            'fitsTime' => $fitsTime,
            'estimate' => $estimate,
            'endsAt' => $estimate !== null
                ? Carbon::parse($this->tournament->start_time)->addMinutes($estimate)->format('H:i')
                : null,
        ])->title($this->tournament->name.' · Parejas');
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Models\TennisMatch;
use App\Services\TournamentManager;
use Illuminate\Validation\ValidationException;

/** Introducir y corregir resultados desde cualquier pantalla del torneo. */
trait EditsResults
{
    /** @var array<int, array{g1: string|int|null, g2: string|int|null}> marcadores en edición, por partido */
    public array $scores = [];

    public ?int $editing = null;

    public function save(int $matchId, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        $match = $this->findMatch($matchId);
        $g1 = $this->scores[$matchId]['g1'] ?? null;
        $g2 = $this->scores[$matchId]['g2'] ?? null;

        // Desde otro móvil ya se ha guardado el resultado mientras se tecleaba este.
        if ($match->isFinished() && $this->editing !== $matchId) {
            unset($this->scores[$matchId]);
            $this->addError("score.$matchId", 'Este partido ya tiene resultado (lo ha guardado otra persona). Si hay que corregirlo, hazlo desde Grupos o Cuadro.');

            return;
        }

        if (! is_numeric($g1) || ! is_numeric($g2)) {
            $this->addError("score.$matchId", 'Introduce los juegos de las dos parejas.');

            return;
        }

        try {
            $manager->recordResult($match, (int) $g1, (int) $g2);
        } catch (ValidationException $e) {
            $this->addError("score.$matchId", collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->scores[$matchId]);
        $this->editing = null;
        $this->tournament->refresh();
    }

    public function edit(int $matchId): void
    {
        $this->authorize('manage', $this->tournament);

        $match = $this->findMatch($matchId);
        abort_unless($match->isFinished(), 422);

        $this->resetErrorBag();
        $this->editing = $matchId;
        $this->scores[$matchId] = ['g1' => $match->games1, 'g2' => $match->games2];
    }

    public function cancelEdit(): void
    {
        unset($this->scores[$this->editing]);
        $this->editing = null;
    }

    protected function findMatch(int $id): TennisMatch
    {
        return $this->tournament->matches()->findOrFail($id);
    }
}

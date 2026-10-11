<?php

namespace App\Livewire\Concerns;

use App\Models\TennisMatch;
use App\Services\TournamentManager;
use Illuminate\Validation\ValidationException;

/** Introducir y corregir resultados desde cualquier pantalla del torneo. */
trait EditsResults
{
    /**
     * Marcadores en edición, por partido: juegos del set o, a 2 sets, cada set
     * (el tercero es el súper tie-break).
     *
     * @var array<int, array{g1?: string|int|null, g2?: string|int|null, sets?: array<int, array<int, string|int|null>>}>
     */
    public array $scores = [];

    public ?int $editing = null;

    public function save(int $matchId, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        $match = $this->findMatch($matchId);

        // Desde otro móvil ya se ha guardado el resultado mientras se tecleaba este.
        if ($match->isFinished() && $this->editing !== $matchId) {
            unset($this->scores[$matchId]);
            $this->addError("score.$matchId", 'Este partido ya tiene resultado (lo ha guardado otra persona). Si hay que corregirlo, hazlo desde Grupos o Cuadro.');

            return;
        }

        $sets = $this->enteredSets($match);
        if ($sets === null) {
            $this->addError("score.$matchId", $match->isTwoSets() ? 'Introduce los juegos de las dos parejas en cada set.' : 'Introduce los juegos de las dos parejas.');

            return;
        }

        try {
            $manager->recordSets($match, $sets);
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
        $this->scores[$matchId] = $match->sets ? ['sets' => $match->sets] : ['g1' => $match->games1, 'g2' => $match->games2];
    }

    public function cancelEdit(): void
    {
        unset($this->scores[$this->editing]);
        $this->editing = null;
    }

    /**
     * Sets tecleados, o null si falta algún número. El súper tie-break vacío no cuenta.
     *
     * @return list<array{int, int}>|null
     */
    private function enteredSets(TennisMatch $match): ?array
    {
        $score = $this->scores[$match->id] ?? [];
        $sets = $match->isTwoSets()
            ? collect(range(0, 2))->map(fn (int $set) => [$score['sets'][$set][0] ?? null, $score['sets'][$set][1] ?? null])
                ->reject(fn (array $set, int $index) => $index === 2 && blank($set[0]) && blank($set[1]))
            : collect([[$score['g1'] ?? null, $score['g2'] ?? null]]);

        if ($sets->flatten()->contains(fn ($games) => ! is_numeric($games))) {
            return null;
        }

        return $sets->map(fn (array $set) => [(int) $set[0], (int) $set[1]])->values()->all();
    }

    protected function findMatch(int $id): TennisMatch
    {
        return $this->tournament->matches()->findOrFail($id);
    }
}

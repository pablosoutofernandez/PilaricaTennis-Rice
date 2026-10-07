<?php

namespace App\Livewire;

use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\TournamentManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class MatchBoard extends Component
{
    public Tournament $tournament;

    /** @var array<int, array{g1: string|int, g2: string|int}> marcadores en edición, por partido */
    public array $scores = [];

    public ?int $editing = null;

    public function save(int $matchId, TournamentManager $manager): void
    {
        $match = $this->findMatch($matchId);
        $g1 = $this->scores[$matchId]['g1'] ?? null;
        $g2 = $this->scores[$matchId]['g2'] ?? null;

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
        $match = $this->findMatch($matchId);
        $this->editing = $matchId;
        $this->scores[$matchId] = ['g1' => $match->games1, 'g2' => $match->games2];
    }

    public function cancelEdit(): void
    {
        unset($this->scores[$this->editing]);
        $this->editing = null;
    }

    public function postpone(int $matchId, TournamentManager $manager): void
    {
        $manager->postpone($this->findMatch($matchId));
        unset($this->scores[$matchId]);
    }

    public function callToCourt(int $matchId, int $court, TournamentManager $manager): void
    {
        try {
            $manager->sendToCourt($this->findMatch($matchId), $court);
        } catch (ValidationException $e) {
            $this->addError('court', collect($e->errors())->flatten()->first());
        }
    }

    public function setStartGames(string $stage, int $games): void
    {
        abort_unless(in_array($stage, ['groups', 'knockout']) && $games >= 0 && $games <= 4, 422);
        $this->tournament->update(["start_games_{$stage}" => $games]);
    }

    private function findMatch(int $id): TennisMatch
    {
        return $this->tournament->matches()->findOrFail($id);
    }

    public function render(TournamentManager $manager)
    {
        $this->tournament->refresh();
        $t = $this->tournament;

        $playing = $t->matches()->with(['pair1', 'pair2', 'group', 'tournament'])
            ->where('status', TennisMatch::PLAYING)->get()->keyBy('court');

        $upcoming = $manager->upcoming($t);
        $busy = $manager->busyPairIds($t);

        $finished = $t->matches()->with(['pair1', 'pair2', 'group', 'tournament'])
            ->where('status', TennisMatch::FINISHED)
            ->orderByDesc('finished_at')->orderByDesc('id')->get();

        $waiting = $t->matches()->where('status', TennisMatch::PENDING)
            ->where(fn ($q) => $q->whereNull('pair1_id')->orWhereNull('pair2_id'))->count();

        return view('livewire.match-board', [
            'playing' => $playing,
            'upcoming' => $upcoming,
            'busy' => $busy,
            'eta' => $this->estimateStartTimes($playing, $upcoming),
            'finished' => $finished,
            'waiting' => $waiting,
            'total' => $t->matches()->count(),
        ])->title($t->name.' · Partidos');
    }

    /**
     * Hora aproximada de inicio de cada partido de la cola: cada uno entra en la pista
     * que antes quede libre según la duración media del partido y el cambio de pista.
     *
     * @return array<int, string> id partido => H:i
     */
    private function estimateStartTimes($playing, $upcoming): array
    {
        $minutes = config('torneo.match_minutes');
        $changeover = config('torneo.changeover_minutes');
        $free = [];

        for ($court = 1; $court <= $this->tournament->courts; $court++) {
            $m = $playing->get($court);
            $free[] = $m
                ? max(now(), Carbon::parse($m->started_at)->addMinutes($minutes[$m->startGames()] + $changeover))
                : now();
        }

        $eta = [];
        foreach ($upcoming as $m) {
            sort($free);
            $start = array_shift($free);
            $eta[$m->id] = $start->format('H:i');
            $free[] = $start->copy()->addMinutes($minutes[$m->startGames()] + $changeover);
        }

        return $eta;
    }
}

<?php

namespace App\Livewire;

use App\Livewire\Concerns\EditsResults;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\FinishEstimator;
use App\Services\TournamentManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class MatchBoard extends Component
{
    use EditsResults;

    public Tournament $tournament;

    public function postpone(int $matchId, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        $manager->postpone($this->findMatch($matchId));
        unset($this->scores[$matchId]);
    }

    public function callToCourt(int $matchId, int $court, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        try {
            $manager->sendToCourt($this->findMatch($matchId), $court);
        } catch (ValidationException $e) {
            $this->addError('court', collect($e->errors())->flatten()->first());
        }
    }

    public function walkover(int $matchId, int $absentPairId, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        try {
            $manager->walkover($this->findMatch($matchId), $absentPairId);
        } catch (ValidationException $e) {
            $this->addError('court', collect($e->errors())->flatten()->first());
        }

        unset($this->scores[$matchId]);
    }

    public function closeCourt(int $court, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        $manager->closeCourt($this->tournament, $court);
    }

    public function openCourt(int $court, TournamentManager $manager): void
    {
        $this->authorize('manage', $this->tournament);

        $manager->openCourt($this->tournament, $court);
    }

    public function setStartGames(string $stage, int $games): void
    {
        $this->authorize('manage', $this->tournament);

        abort_unless(in_array($stage, ['groups', 'knockout']) && $games >= 0 && $games <= 4, 422);
        $this->tournament->update(["start_games_{$stage}" => $games]);
    }

    public function render(TournamentManager $manager, FinishEstimator $estimator)
    {
        $this->tournament->refresh();
        $t = $this->tournament;

        $playing = $t->matches()->with(['pair1', 'pair2', 'group', 'tournament'])
            ->where('status', TennisMatch::PLAYING)->get()->keyBy('court');

        $upcoming = $manager->upcoming($t);
        $schedule = $this->schedule($playing, $upcoming, $estimator->currentPace($t));

        $waiting = $t->matches()->where('status', TennisMatch::PENDING)
            ->where(fn ($q) => $q->whereNull('pair1_id')->orWhereNull('pair2_id'))->count();

        return view('livewire.match-board', [
            'playing' => $playing,
            'upcoming' => $upcoming,
            'nextOnCourt' => $upcoming->whereNotNull('next_on_court')->keyBy('next_on_court'),
            'afterNext' => $upcoming->whereNull('next_on_court')
                ->sortBy(fn (TennisMatch $match) => [$schedule[$match->id] ?? now(), $match->queue_order])->values(),
            'busy' => $manager->busyPairIds($t),
            'eta' => $schedule,
            'waiting' => $waiting,
            'played' => $t->matches()->where('status', TennisMatch::FINISHED)->count(),
            'total' => $t->matches()->count(),
            'canManage' => auth()->user()?->can('manage', $t) ?? false,
            'organizerNames' => $t->organizerNames(),
            'knockoutStartOptions' => $this->knockoutStartOptions($estimator),
        ])->title($t->name.' · Partidos');
    }

    /**
     * Hora de fin estimada según a cuánto empiecen los partidos de la fase final que aún
     * no se han jugado, para decidir si hay que acortarlos.
     *
     * @return array<int, Carbon> marcador inicial => fin estimado
     */
    private function knockoutStartOptions(FinishEstimator $estimator): array
    {
        if (! in_array($this->tournament->status, [Tournament::GROUPS, Tournament::KNOCKOUT])
            || ! auth()->user()?->can('manage', $this->tournament)) {
            return [];
        }

        $options = [];
        foreach (array_keys(config('torneo.match_minutes')) as $startGames) {
            $hypothetical = clone $this->tournament;
            $hypothetical->start_games_knockout = $startGames;
            $options[$startGames] = $estimator->estimate($hypothetical)['finish'];
        }

        return $options;
    }

    /**
     * Hora aproximada de cada partido de la cola. El siguiente de cada pista entra en ella;
     * el resto, igual que al ocupar pistas, en la que antes quede libre el primero cuyas
     * parejas no estén jugando. La duración sigue el ritmo real del torneo.
     *
     * @param  Collection<int, TennisMatch>  $playing  partidos en juego por pista
     * @param  Collection<int, TennisMatch>  $upcoming
     * @return array<int, Carbon> id partido => hora aproximada
     */
    private function schedule(Collection $playing, Collection $upcoming, float $pace): array
    {
        $courts = $this->tournament->openCourts();
        if ($courts === []) {
            return [];
        }

        $slot = fn (TennisMatch $match) => (int) round((config('torneo.match_minutes')[$match->startGames()] + config('torneo.changeover_minutes')) * $pace * 60);
        $courtFreeAt = [];
        $pairFreeAt = [];

        foreach ($courts as $court) {
            $courtFreeAt[$court] = now();
            if ($match = $playing->get($court)) {
                $courtFreeAt[$court] = $match->started_at->copy()->addSeconds($slot($match))->max(now());
                $pairFreeAt[$match->pair1_id] = $pairFreeAt[$match->pair2_id] = $courtFreeAt[$court];
            }
        }

        $queue = $upcoming->values()->all();
        $schedule = [];
        $startAt = fn (Carbon $freeAt, TennisMatch $match) => collect([$freeAt, $pairFreeAt[$match->pair1_id] ?? null, $pairFreeAt[$match->pair2_id] ?? null])->filter()->max();

        // Los avisados como siguientes entran en su pista en cuanto quede libre.
        asort($courtFreeAt);
        foreach (array_keys($courtFreeAt) as $court) {
            $index = collect($queue)->search(fn (TennisMatch $match) => $match->next_on_court === $court);
            if ($index === false) {
                continue;
            }

            $match = $queue[$index];
            array_splice($queue, $index, 1);
            $schedule[$match->id] = $startAt($courtFreeAt[$court], $match);
            $courtFreeAt[$court] = $pairFreeAt[$match->pair1_id] = $pairFreeAt[$match->pair2_id] = $schedule[$match->id]->copy()->addSeconds($slot($match));
        }

        while ($queue) {
            asort($courtFreeAt);
            $court = array_key_first($courtFreeAt);
            $freeAt = $courtFreeAt[$court];

            $availableAt = fn (TennisMatch $match) => $startAt($freeAt, $match);
            $index = collect($queue)->search(fn (TennisMatch $match) => $availableAt($match) <= $freeAt);
            if ($index === false) {
                $index = collect($queue)->keys()->sortBy(fn (int $key) => $availableAt($queue[$key]))->first();
            }

            $match = $queue[$index];
            array_splice($queue, $index, 1);

            $start = $availableAt($match);
            $schedule[$match->id] = $start;
            $courtFreeAt[$court] = $pairFreeAt[$match->pair1_id] = $pairFreeAt[$match->pair2_id] = $start->copy()->addSeconds($slot($match));
        }

        return $schedule;
    }
}

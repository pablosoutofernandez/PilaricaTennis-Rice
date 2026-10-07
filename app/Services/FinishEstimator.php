<?php

namespace App\Services;

use App\Models\TennisMatch;
use App\Models\Tournament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hora estimada de fin de un torneo en juego, recalculada con lo que queda por jugar.
 *
 * Simula el reparto de los partidos pendientes (también las rondas de los cuadros que aún
 * no se han generado) entre las pistas, respetando que una ronda no empieza hasta que
 * termina la anterior. La duración de cada partido se ajusta al ritmo real que lleva el torneo.
 */
class FinishEstimator
{
    /** Partidos terminados necesarios para fiarse del ritmo real en lugar del margen de organización. */
    private const MIN_MATCHES_FOR_PACE = 2;

    public function __construct(private FormatPlanner $planner) {}

    /**
     * @return array{finish: Carbon, scheduled_finish: Carbon, delay_minutes: int, pace: float, finished: bool, paused: bool, average_minutes: int, average_is_real: bool}|null
     */
    public function estimate(Tournament $tournament): ?array
    {
        if ($tournament->isRegistration()) {
            return null;
        }

        $scheduledFinish = $tournament->date->copy()->setTimeFromTimeString($tournament->end_time);
        $matches = $tournament->matches()->get();
        $finishedMatches = $matches->where('status', TennisMatch::FINISHED);

        if ($tournament->status === Tournament::FINISHED) {
            $finish = $finishedMatches->max('finished_at') ?? now();

            $pace = $this->pace($finishedMatches, $tournament);

            return $this->result($finish, $scheduledFinish, $pace, true) + $this->averageMatch($finishedMatches, $tournament, $pace);
        }

        $pace = $this->pace($finishedMatches, $tournament);
        $slot = fn (int $startGames) => config('torneo.match_minutes')[$startGames] + config('torneo.changeover_minutes');

        $courtsFreeAt = [];
        $ends = [];
        $playing = $matches->where('status', TennisMatch::PLAYING)->keyBy('court');

        // Con todas las pistas cerradas se estima como si se reabrieran ahora.
        $paused = $tournament->openCourts() === [];

        foreach ($paused ? range(1, $tournament->courts) : $tournament->openCourts() as $court) {
            $match = $playing->get($court);
            $courtsFreeAt[$court] = now();

            if ($match) {
                $end = $match->started_at->copy()->addSeconds((int) round($slot($match->startGames()) * $pace * 60));
                $courtsFreeAt[$court] = $end->max(now());
                $ends[$match->stage][$match->round][] = $courtsFreeAt[$court];
            }
        }

        $pending = $this->pendingMatches($tournament, $matches);
        $finish = collect($courtsFreeAt)->max();

        while ($pending->isNotEmpty()) {
            asort($courtsFreeAt);
            $court = array_key_first($courtsFreeAt);
            $courtFreeAt = $courtsFreeAt[$court];

            $candidates = $pending
                ->reject(fn (array $match) => $this->isBlocked($tournament, $match, $pending))
                ->map(fn (array $match) => $match + ['ready_at' => $this->readyAt($tournament, $match, $ends)]);

            $next = $candidates->filter(fn (array $match) => $match['ready_at'] === null || $match['ready_at'] <= $courtFreeAt)->sortBy('order')->first()
                ?? $candidates->sortBy(fn (array $match) => [$match['ready_at'], $match['order']])->first();

            if (! $next) {
                break;
            }

            $start = $next['ready_at'] === null ? $courtFreeAt : $courtFreeAt->max($next['ready_at']);
            $end = $start->copy()->addSeconds((int) round($slot($next['start_games']) * $pace * 60));

            $courtsFreeAt[$court] = $end;
            $ends[$next['stage']][$next['round']][] = $end;
            $finish = $finish->max($end);
            $pending->forget($next['key']);
        }

        // Tras el último partido no hay cambio de pista.
        $finish = $finish->copy()->subSeconds((int) round(config('torneo.changeover_minutes') * $pace * 60))->max(now());

        return $this->result($finish, $scheduledFinish, $pace, false, $paused) + $this->averageMatch($finishedMatches, $tournament, $pace);
    }

    /**
     * Partidos que quedan por empezar, incluidos los de cuadros que aún no existen.
     *
     * @param  Collection<int, TennisMatch>  $matches
     * @return Collection<string, array{key: string, stage: string, round: int, start_games: int, order: int}>
     */
    private function pendingMatches(Tournament $tournament, Collection $matches): Collection
    {
        $pending = $matches->where('status', TennisMatch::PENDING)
            ->map(fn (TennisMatch $match) => [
                'key' => "m{$match->id}",
                'stage' => $match->stage,
                'round' => $match->round,
                'start_games' => (int) ($match->isGroup() ? $tournament->start_games_groups : $tournament->start_games_knockout),
                'order' => $match->queue_order,
            ])
            ->keyBy('key');

        $order = (int) $matches->max('queue_order');
        $addDraw = function (string $stage, int $entrants) use ($tournament, $pending, &$order): void {
            foreach ($this->planner->knockoutRoundMatchCounts($entrants) as $index => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $key = "{$stage}-{$index}-{$i}";
                    $pending->put($key, [
                        'key' => $key,
                        'stage' => $stage,
                        'round' => $index + 1,
                        'start_games' => (int) $tournament->start_games_knockout,
                        'order' => ++$order,
                    ]);
                }
            }
        };

        $activePairs = $tournament->pairs()->whereNull('withdrawn_at')->count();
        $qualifiers = min((int) $tournament->qualifiers, $activePairs);

        if ($tournament->status === Tournament::GROUPS) {
            $addDraw('knockout', $qualifiers);
        }

        if (! $matches->contains('stage', 'consolation')) {
            $addDraw('consolation', $tournament->consolation_all
                ? $activePairs - $qualifiers
                : intdiv($qualifiers, 2));
        }

        return $pending;
    }

    /**
     * Rondas que tienen que haber terminado antes de poder jugar un partido.
     *
     * @param  array{stage: string, round: int}  $match
     * @return array<int, array{stage: string, before_round: ?int}> before_round null = toda la fase
     */
    private function prerequisites(Tournament $tournament, array $match): array
    {
        return match ($match['stage']) {
            'knockout' => [['stage' => 'group', 'before_round' => null], ['stage' => 'knockout', 'before_round' => $match['round']]],
            'consolation' => [
                $tournament->consolation_all
                    ? ['stage' => 'group', 'before_round' => null]
                    : ['stage' => 'knockout', 'before_round' => 2],
                ['stage' => 'consolation', 'before_round' => $match['round']],
            ],
            default => [],
        };
    }

    /** Si algún partido previo necesario todavía no tiene hueco en la simulación. */
    private function isBlocked(Tournament $tournament, array $match, Collection $pending): bool
    {
        foreach ($this->prerequisites($tournament, $match) as $prerequisite) {
            $waiting = $pending->contains(fn (array $other) => $other['stage'] === $prerequisite['stage']
                && ($prerequisite['before_round'] === null || $other['round'] < $prerequisite['before_round']));

            if ($waiting) {
                return true;
            }
        }

        return false;
    }

    /** Momento en el que terminan los partidos previos necesarios (null si no hay que esperar). */
    private function readyAt(Tournament $tournament, array $match, array $ends): ?Carbon
    {
        $readyAt = null;

        foreach ($this->prerequisites($tournament, $match) as $prerequisite) {
            foreach ($ends[$prerequisite['stage']] ?? [] as $round => $roundEnds) {
                if ($prerequisite['before_round'] === null || $round < $prerequisite['before_round']) {
                    $readyAt = collect($roundEnds)->push($readyAt)->filter()->max();
                }
            }
        }

        return $readyAt;
    }

    /** Ritmo actual del torneo (1 = los partidos duran lo previsto). */
    public function currentPace(Tournament $tournament): float
    {
        return $this->pace($tournament->matches()->where('status', TennisMatch::FINISHED)->get(), $tournament);
    }

    /**
     * Relación entre lo que están durando los partidos y lo previsto. Hasta tener
     * suficientes partidos se usa el margen de organización.
     *
     * @param  Collection<int, TennisMatch>  $finishedMatches
     */
    private function pace(Collection $finishedMatches, Tournament $tournament): float
    {
        $timed = $this->timedMatches($finishedMatches);

        if ($timed->count() < self::MIN_MATCHES_FOR_PACE) {
            return 1 + config('torneo.organization_margin');
        }

        $expected = $timed->sum(fn (TennisMatch $match) => config('torneo.match_minutes')[$match->startGames()] + config('torneo.changeover_minutes'));
        $actual = $timed->sum(fn (TennisMatch $match) => $match->started_at->diffInSeconds($match->finished_at) / 60);

        return round(min(2, max(0.5, $actual / $expected)), 2);
    }

    /**
     * Minutos que ocupa de media un partido en pista. Hasta que termina alguno, lo previsto
     * para el marcador de la fase actual.
     *
     * @param  Collection<int, TennisMatch>  $finishedMatches
     * @return array{average_minutes: int, average_is_real: bool}
     */
    private function averageMatch(Collection $finishedMatches, Tournament $tournament, float $pace): array
    {
        $timed = $this->timedMatches($finishedMatches);

        if ($timed->isNotEmpty()) {
            return [
                'average_minutes' => (int) round($timed->avg(fn (TennisMatch $match) => $match->started_at->diffInSeconds($match->finished_at) / 60)),
                'average_is_real' => true,
            ];
        }

        $startGames = (int) ($tournament->status === Tournament::GROUPS ? $tournament->start_games_groups : $tournament->start_games_knockout);

        return [
            'average_minutes' => (int) round((config('torneo.match_minutes')[$startGames] + config('torneo.changeover_minutes')) * $pace),
            'average_is_real' => false,
        ];
    }

    /**
     * Partidos jugados de verdad en pista: los W.O. no cuentan para el ritmo.
     *
     * @param  Collection<int, TennisMatch>  $finishedMatches
     * @return Collection<int, TennisMatch>
     */
    private function timedMatches(Collection $finishedMatches): Collection
    {
        return $finishedMatches->filter(fn (TennisMatch $match) => $match->started_at && $match->finished_at && ! $match->walkover);
    }

    /** @return array{finish: Carbon, scheduled_finish: Carbon, delay_minutes: int, pace: float, finished: bool, paused: bool} */
    private function result(Carbon $finish, Carbon $scheduledFinish, float $pace, bool $finished, bool $paused = false): array
    {
        return [
            'finish' => $finish,
            'scheduled_finish' => $scheduledFinish,
            'delay_minutes' => (int) round($scheduledFinish->diffInMinutes($finish, false)),
            'pace' => $pace,
            'finished' => $finished,
            'paused' => $paused,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Pair;
use App\Models\TennisMatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentManager
{
    public function __construct(private FormatPlanner $planner) {}

    /* ------------------------------------------------------------------
     |  Inicio: sorteo de grupos y calendario
     * ------------------------------------------------------------------ */

    public function start(Tournament $tournament): void
    {
        $proposal = $tournament->proposal();
        $startGroups = $tournament->start_games_groups ?? $proposal['start_games'] ?? 0;
        $startKnockout = $tournament->start_games_knockout ?? $proposal['start_games_knockout'] ?? 0;
        $fitsTime = $proposal && $this->planner->estimate($proposal, $startGroups, $startKnockout) <= $tournament->availableMinutes();

        if (! $tournament->isRegistration() || ! $proposal || ! $proposal['fits'] || ! $fitsTime) {
            throw ValidationException::withMessages([
                'start' => 'La propuesta no cabe en el horario disponible o no hay suficientes parejas.',
            ]);
        }

        DB::transaction(function () use ($tournament, $proposal, $startGroups, $startKnockout) {
            $tournament->update([
                'status' => Tournament::GROUPS,
                'group_sizes' => $proposal['group_sizes'],
                'qualifiers' => $proposal['qualifiers'],
                'start_games_groups' => $startGroups,
                'start_games_knockout' => $startKnockout,
            ]);

            $groups = $this->drawGroups($tournament, $proposal['group_sizes']);
            $this->createGroupMatches($tournament, $groups);
        });

        $this->fillCourts($tournament);
    }

    /**
     * Cabezas de serie repartidos en "serpiente" (A B C C B A...), el resto al azar.
     *
     * @return Collection<Group>
     */
    private function drawGroups(Tournament $tournament, array $sizes): Collection
    {
        $groups = collect($sizes)->map(fn ($size, $i) => $tournament->groups()->create([
            'name' => chr(65 + $i),
        ]));

        $pairs = $tournament->pairs()->get();
        $ordered = $pairs->where('seeded', true)->shuffle()->concat($pairs->where('seeded', false)->shuffle());

        $slots = [];
        $count = count($sizes);
        for ($round = 0; count($slots) < $pairs->count(); $round++) {
            $indexes = $round % 2 === 0 ? range(0, $count - 1) : range($count - 1, 0);
            foreach ($indexes as $i) {
                if ($round < $sizes[$i]) {
                    $slots[] = $i;
                }
            }
        }

        foreach ($ordered->values() as $k => $pair) {
            $pair->update(['group_id' => $groups[$slots[$k]]->id]);
        }

        return $groups;
    }

    /** Todos contra todos (método del círculo), intercalando las jornadas de cada grupo. */
    private function createGroupMatches(Tournament $tournament, Collection $groups): void
    {
        $byRound = [];

        foreach ($groups as $group) {
            $ids = $group->pairs()->pluck('id')->all();
            foreach ($this->roundRobin($ids) as $r => $games) {
                foreach ($games as $game) {
                    $byRound[$r][] = [$group->id, $game[0], $game[1]];
                }
            }
        }

        $order = 0;
        foreach ($byRound as $r => $games) {
            // Alterna grupos dentro de la jornada para que nadie juegue dos seguidos.
            usort($games, fn ($a, $b) => $a[0] <=> $b[0]);
            foreach ($this->interleaveByGroup($games) as [$groupId, $p1, $p2]) {
                $tournament->matches()->create([
                    'stage' => 'group',
                    'group_id' => $groupId,
                    'round' => $r + 1,
                    'pair1_id' => $p1,
                    'pair2_id' => $p2,
                    'queue_order' => ++$order,
                ]);
            }
        }
    }

    /** @return array<int, array<int, array{int,int}>> jornadas => partidos */
    public function roundRobin(array $ids): array
    {
        if (count($ids) % 2 === 1) {
            $ids[] = null; // descansa
        }

        $n = count($ids);
        $rounds = [];

        for ($r = 0; $r < $n - 1; $r++) {
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $ids[$i];
                $b = $ids[$n - 1 - $i];
                if ($a !== null && $b !== null) {
                    $rounds[$r][] = $r % 2 === 0 ? [$a, $b] : [$b, $a];
                }
            }
            // Rotación: el primero queda fijo
            $ids = array_merge([$ids[0]], [$ids[$n - 1]], array_slice($ids, 1, $n - 2));
        }

        return $rounds;
    }

    private function interleaveByGroup(array $games): array
    {
        $buckets = [];
        foreach ($games as $game) {
            $buckets[$game[0]][] = $game;
        }

        $result = [];
        while ($buckets) {
            foreach ($buckets as $key => &$bucket) {
                $result[] = array_shift($bucket);
                if (! $bucket) {
                    unset($buckets[$key]);
                }
            }
            unset($bucket);
        }

        return $result;
    }

    /* ------------------------------------------------------------------
     |  Clasificaciones
     * ------------------------------------------------------------------ */

    /**
     * @return Collection<array{pair: Pair, played:int, won:int, lost:int, gf:int, ga:int, diff:int, position:int}>
     */
    public function standings(Group $group): Collection
    {
        $pairs = $group->pairs()->get();
        $matches = $group->matches()->where('status', TennisMatch::FINISHED)->get();

        $rows = $pairs->mapWithKeys(fn (Pair $pair) => [$pair->id => [
            'pair' => $pair, 'played' => 0, 'won' => 0, 'lost' => 0, 'gf' => 0, 'ga' => 0, 'diff' => 0,
        ]])->all();

        foreach ($matches as $m) {
            foreach ([[$m->pair1_id, $m->games1, $m->games2], [$m->pair2_id, $m->games2, $m->games1]] as [$id, $for, $against]) {
                $rows[$id]['played']++;
                $rows[$id]['gf'] += $for;
                $rows[$id]['ga'] += $against;
                $rows[$id]['diff'] = $rows[$id]['gf'] - $rows[$id]['ga'];
                $m->winner_id === $id ? $rows[$id]['won']++ : $rows[$id]['lost']++;
            }
        }

        // 1º victorias, 2º diferencia de juegos, 3º juegos a favor
        usort($rows, fn ($a, $b) => [$b['won'], $b['diff'], $b['gf']] <=> [$a['won'], $a['diff'], $a['gf']]);

        // Empate a victorias entre exactamente dos parejas: decide el enfrentamiento directo.
        $byWins = collect($rows)->countBy('won');
        for ($i = 0; $i < count($rows) - 1; $i++) {
            [$row, $next] = [$rows[$i], $rows[$i + 1]];
            if ($next['won'] === $row['won'] && $byWins[$row['won']] === 2) {
                $h2h = $matches->first(fn ($m) => in_array($m->pair1_id, [$row['pair']->id, $next['pair']->id])
                    && in_array($m->pair2_id, [$row['pair']->id, $next['pair']->id]));
                if ($h2h && $h2h->winner_id === $next['pair']->id) {
                    [$rows[$i], $rows[$i + 1]] = [$next, $row];
                }
                $i++;
            }
        }

        return collect($rows)->values()->map(fn ($row, $i) => $row + ['position' => $i + 1]);
    }

    /* ------------------------------------------------------------------
     |  Fase final
     * ------------------------------------------------------------------ */

    public function groupStageFinished(Tournament $tournament): bool
    {
        return ! $tournament->matches()->where('stage', 'group')
            ->where('status', '!=', TennisMatch::FINISHED)->exists();
    }

    public function generateKnockout(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            $tournament->matches()->whereIn('stage', ['knockout', 'consolation'])->delete();

            $seeds = $this->seededQualifiers($tournament);
            $order = (int) $tournament->matches()->max('queue_order');
            $this->generateDraw($tournament, 'knockout', $seeds, $order, false);
            if ($tournament->consolation_all) {
                $this->generateConsolation($tournament);
            }

            $tournament->update(['status' => Tournament::KNOCKOUT]);
        });

        $this->fillCourts($tournament);
    }

    private function consolationEntrants(Tournament $tournament): array
    {
        if ($tournament->consolation_all) {
            return array_slice($this->rankedPairs($tournament), $tournament->qualifiers);
        }

        $pairIds = $tournament->matches()->where('stage', 'knockout')
            ->where('round', 1)
            ->where('status', TennisMatch::FINISHED)
            ->get()
            ->map(fn (TennisMatch $match) => $match->winner_id === $match->pair1_id ? $match->pair2_id : $match->pair1_id)
            ->unique()
            ->values();

        return $tournament->pairs()->with('group')->whereIn('id', $pairIds)
            ->orderBy('id')->get()
            ->map(fn (Pair $pair, int $index) => [
                'pair' => $pair,
                'group' => $pair->group?->name ?? '',
                'position' => $index + 1,
            ])->all();
    }

    private function generateConsolation(Tournament $tournament): void
    {
        $order = (int) $tournament->matches()->max('queue_order');
        $this->generateDraw($tournament, 'consolation', $this->consolationEntrants($tournament), $order, false);
    }

    /** @param array<int, array{pair: Pair, group: string, position: int}> $seeds */
    private function generateDraw(Tournament $tournament, string $stage, array $seeds, int $order, bool $thirdPlace): int
    {
        $bracketSize = 2;
        while ($bracketSize < count($seeds)) {
            $bracketSize *= 2;
        }

        $seeds = array_values($seeds);
        $slots = array_map(fn (int $seed) => $seeds[$seed - 1] ?? null, $this->bracketOrder($bracketSize));

        if ($stage === 'knockout' && $tournament->groups()->count() > 1) {
            $firstRound = [];
            for ($index = 0; $index < $bracketSize; $index += 2) {
                $firstRound[] = [$slots[$index], $slots[$index + 1]];
            }
            $slots = array_merge(...$this->avoidSameGroup($firstRound));
        }

        $potential = array_map(fn ($pair) => $pair !== null, $slots);

        for ($bracket = $bracketSize, $round = 1; $bracket >= 2; $bracket /= 2, $round++) {
            $nextSlots = [];
            $nextPotential = [];

            for ($index = 0; $index < $bracket; $index += 2) {
                $left = $slots[$index] ?? null;
                $right = $slots[$index + 1] ?? null;
                $leftPotential = $potential[$index] ?? false;
                $rightPotential = $potential[$index + 1] ?? false;

                if (! $leftPotential && ! $rightPotential) {
                    $nextSlots[] = null;
                    $nextPotential[] = false;

                    continue;
                }

                if ($leftPotential && $rightPotential) {
                    $position = (int) ($index / 2) + 1;
                    $tournament->matches()->create([
                        'stage' => $stage,
                        'round' => $round,
                        'bracket_size' => $bracket,
                        'position' => $position,
                        'pair1_id' => $left['pair']->id ?? null,
                        'pair2_id' => $right['pair']->id ?? null,
                        'queue_order' => ++$order,
                    ]);
                    $nextSlots[] = null;
                    $nextPotential[] = true;

                    continue;
                }

                $nextSlots[] = $leftPotential ? $left : $right;
                $nextPotential[] = true;
            }

            $slots = $nextSlots;
            $potential = $nextPotential;

            if ($bracket === 2 && $thirdPlace && count($seeds) >= 4) {
                $tournament->matches()->create([
                    'stage' => $stage,
                    'round' => $round,
                    'bracket_size' => 2,
                    'position' => 2,
                    'third_place' => true,
                    'queue_order' => ++$order,
                ]);
            }
        }

        return $order;
    }

    /**
     * Clasificados ordenados como cabezas de serie: primero todos los 1º, luego los 2º...
     * y por último los mejores de la siguiente posición.
     *
     * @return array<int, array{pair: Pair, group: string, position: int}>
     */
    public function seededQualifiers(Tournament $tournament): array
    {
        return array_slice($this->rankedPairs($tournament), 0, $tournament->qualifiers);
    }

    /**
     * Todas las parejas ordenadas por puesto de grupo. Entre grupos de distinto tamaño
     * se compara por % de victorias y diferencia de juegos por partido.
     *
     * @return array<int, array{pair: Pair, group: string, position: int}>
     */
    private function rankedPairs(Tournament $tournament): array
    {
        $groups = $tournament->groups()->get();
        $standings = $groups->mapWithKeys(fn (Group $g) => [$g->name => $this->standings($g)]);
        $largestGroup = $standings->map(fn ($rows) => count($rows))->max() ?? 0;

        $tier = function (int $position) use ($standings) {
            return $standings
                ->map(fn ($rows, $name) => ($rows[$position - 1] ?? null) ? $rows[$position - 1] + ['group' => $name] : null)
                ->filter()
                ->sortByDesc(fn ($r) => [
                    $r['played'] ? $r['won'] / $r['played'] : 0,
                    $r['played'] ? $r['diff'] / $r['played'] : 0,
                    $r['played'] ? $r['gf'] / $r['played'] : 0,
                ])
                ->values();
        };

        $ranked = collect();
        for ($position = 1; $position <= $largestGroup; $position++) {
            $ranked = $ranked->concat($tier($position));
        }

        return $ranked->values()->all();
    }

    /** Orden estándar de cabezas de serie: 8 => [1,8,4,5,2,7,3,6] */
    public function bracketOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $n = count($order) * 2;
            $order = collect($order)->flatMap(fn ($s) => [$s, $n + 1 - $s])->all();
        }

        return $order;
    }

    /** Evita que dos parejas del mismo grupo se crucen en la primera ronda. */
    private function avoidSameGroup(array $matches): array
    {
        $conflict = fn ($m) => $m[0] !== null && $m[1] !== null && $m[0]['group'] === $m[1]['group'];

        for ($i = 0; $i < count($matches); $i++) {
            $m = $matches[$i];
            if (! $conflict($m)) {
                continue;
            }
            // Primero intenta intercambiar con una pareja de la misma posición de grupo
            foreach ([true, false] as $samePosition) {
                foreach ($matches as $j => $other) {
                    if ($i === $j || $other[0] === null || $other[1] === null
                        || ($samePosition && $other[1]['position'] !== $m[1]['position'])) {
                        continue;
                    }
                    $a = [$m[0], $other[1]];
                    $b = [$other[0], $m[1]];
                    if (! $conflict($a) && ! $conflict($b)) {
                        $matches[$i] = $a;
                        $matches[$j] = $b;

                        continue 3;
                    }
                }
            }
        }

        return $matches;
    }

    /* ------------------------------------------------------------------
     |  Resultados
     * ------------------------------------------------------------------ */

    public function validateScore(int $g1, int $g2, int $start): ?string
    {
        $win = max($g1, $g2);
        $lose = min($g1, $g2);

        if ($g1 === $g2) {
            return 'No puede haber empate.';
        }
        if ($lose < $start) {
            return "El set empezó {$start}-{$start}: ninguna pareja puede tener menos de {$start} juegos.";
        }
        if (($win === 6 && $lose <= 4) || ($win === 7 && in_array($lose, [5, 6]))) {
            return null;
        }

        return 'Resultado no válido para un set (6-0 … 6-4, 7-5 o 7-6).';
    }

    public function recordResult(TennisMatch $match, int $g1, int $g2): void
    {
        $tournament = $match->tournament;

        if (! $match->hasBothPairs()) {
            throw ValidationException::withMessages(['score' => 'El partido aún no tiene las dos parejas.']);
        }
        if ($error = $this->validateScore($g1, $g2, $match->startGames())) {
            throw ValidationException::withMessages(['score' => $error]);
        }

        $winner = $g1 > $g2 ? $match->pair1_id : $match->pair2_id;
        $wasFinished = $match->isFinished();
        $winnerChanged = $wasFinished && $match->winner_id !== $winner;

        if ($wasFinished) {
            $this->assertEditable($match, $winnerChanged);
        }

        DB::transaction(function () use ($match, $g1, $g2, $winner, $wasFinished, $winnerChanged, $tournament) {
            $match->update([
                'games1' => $g1,
                'games2' => $g2,
                'winner_id' => $winner,
                'status' => TennisMatch::FINISHED,
                'finished_at' => $match->finished_at ?? now(),
            ]);

            if ($match->isGroup()) {
                // Corrección de un grupo con el cuadro ya generado (sin partidos terminados): se rehace.
                if ($wasFinished && $tournament->status === Tournament::KNOCKOUT) {
                    $this->generateKnockout($tournament);
                }
            } elseif (! $wasFinished || $winnerChanged) {
                $this->advance($match);
            }
        });

        $tournament->refresh();

        if ($tournament->status === Tournament::GROUPS && $this->groupStageFinished($tournament)) {
            $this->generateKnockout($tournament);
        }

        if ($tournament->status === Tournament::KNOCKOUT
            && ! $tournament->consolation_all
            && ! $tournament->matches()->where('stage', 'knockout')->where('round', 1)->where('status', '!=', TennisMatch::FINISHED)->exists()
            && ! $tournament->matches()->where('stage', 'consolation')->exists()) {
            $this->generateConsolation($tournament);
        }

        if ($tournament->status === Tournament::KNOCKOUT
            && ! $tournament->matches()->where('status', '!=', TennisMatch::FINISHED)->exists()) {
            $tournament->update(['status' => Tournament::FINISHED]);
        }

        $this->fillCourts($tournament);
    }

    private function assertEditable(TennisMatch $match, bool $winnerChanged): void
    {
        $tournament = $match->tournament;

        if ($match->isGroup() && $tournament->status !== Tournament::GROUPS) {
            $koStarted = $tournament->matches()->where('stage', 'knockout')
                ->where('status', TennisMatch::FINISHED)->exists();
            if ($koStarted) {
                throw ValidationException::withMessages([
                    'score' => 'La fase final ya tiene resultados: no se pueden cambiar los grupos.',
                ]);
            }
        }

        if (! $match->isGroup() && $winnerChanged) {
            if ($match->stage === 'knockout'
                && $tournament->matches()->where('stage', 'consolation')->exists()) {
                throw ValidationException::withMessages([
                    'score' => 'La consolación ya se ha generado: no se puede cambiar el ganador del cuadro principal.',
                ]);
            }

            $blocked = $this->dependentMatches($match)->contains(fn ($m) => $m->status !== TennisMatch::PENDING);
            if ($blocked) {
                throw ValidationException::withMessages([
                    'score' => 'El siguiente partido de esta pareja ya ha empezado: no se puede cambiar el ganador.',
                ]);
            }
        }
    }

    /** Partidos que dependen del resultado de una eliminatoria (siguiente ronda y 3er puesto). */
    private function dependentMatches(TennisMatch $match): Collection
    {
        if ($match->bracket_size <= 2) {
            return collect();
        }

        $query = fn () => $match->tournament->matches()->where('stage', $match->stage);

        $next = $query()->where('bracket_size', $match->bracket_size / 2)
            ->where('third_place', false)
            ->where('position', (int) ceil($match->position / 2))->get();

        if ($match->stage === 'knockout' && $match->bracket_size === 4) {
            $next = $next->concat($query()->where('third_place', true)->get());
        }

        return $next;
    }

    /** Lleva al ganador a la siguiente ronda (y al perdedor de semis al 3er puesto). */
    private function advance(TennisMatch $match): void
    {
        if ($match->bracket_size <= 2) {
            return;
        }

        $slot = $match->position % 2 === 1 ? 'pair1_id' : 'pair2_id';
        $loser = $match->winner_id === $match->pair1_id ? $match->pair2_id : $match->pair1_id;

        foreach ($this->dependentMatches($match) as $next) {
            $next->update([$slot => $next->third_place ? $loser : $match->winner_id]);
        }
    }

    /* ------------------------------------------------------------------
     |  Pistas y cola de partidos
     * ------------------------------------------------------------------ */

    /** Ocupa las pistas libres con el siguiente partido disponible de la cola. */
    public function fillCourts(Tournament $tournament): void
    {
        if (! in_array($tournament->status, [Tournament::GROUPS, Tournament::KNOCKOUT])) {
            return;
        }

        $playing = $tournament->matches()->where('status', TennisMatch::PLAYING)->get();
        $freeCourts = array_diff(range(1, $tournament->courts), $playing->pluck('court')->all());

        foreach ($freeCourts as $court) {
            $busy = $this->busyPairIds($tournament);
            $next = $this->upcoming($tournament)->first(fn ($m) => $this->isReady($m, $busy));
            if (! $next) {
                return;
            }
            $this->startOnCourt($next, $court);
        }
    }

    /** IDs de las parejas que están ahora mismo en pista. */
    public function busyPairIds(Tournament $tournament): array
    {
        return $tournament->matches()->where('status', TennisMatch::PLAYING)
            ->get()->flatMap(fn ($m) => [$m->pair1_id, $m->pair2_id])->all();
    }

    public function isReady(TennisMatch $match, array $busy): bool
    {
        return ! in_array($match->pair1_id, $busy) && ! in_array($match->pair2_id, $busy);
    }

    /**
     * Partidos pendientes con ambas parejas, en orden de cola. Primero los que pueden
     * empezar ya (ninguna pareja está jugando).
     */
    public function upcoming(Tournament $tournament): Collection
    {
        $busy = $this->busyPairIds($tournament);

        return $tournament->matches()
            ->with(['pair1', 'pair2', 'group', 'tournament'])
            ->where('status', TennisMatch::PENDING)
            ->whereNotNull('pair1_id')->whereNotNull('pair2_id')
            ->orderBy('queue_order')
            ->get()
            ->sortBy(fn ($m) => $this->isReady($m, $busy) ? 0 : 1)
            ->values();
    }

    /** Retrasa un partido unos puestos en la cola. Si se estaba jugando, libera la pista. */
    public function postpone(TennisMatch $match): void
    {
        if ($match->isFinished()) {
            return;
        }

        $tournament = $match->tournament;

        DB::transaction(function () use ($match, $tournament) {
            $match->update([
                'status' => TennisMatch::PENDING,
                'court' => null,
                'started_at' => null,
                'start_games' => null,
                'postponed' => $match->postponed + 1,
            ]);

            $pending = $tournament->matches()->where('status', TennisMatch::PENDING)
                ->orderBy('queue_order')->get();

            $list = $pending->reject(fn ($m) => $m->id === $match->id)->values();
            $index = $pending->search(fn ($m) => $m->id === $match->id);

            // Salta los N siguientes partidos que ya tienen parejas
            $skipped = 0;
            $insertAt = $index;
            for ($i = $index; $i < $list->count() && $skipped < config('torneo.postpone_steps'); $i++) {
                $insertAt = $i + 1;
                if ($list[$i]->hasBothPairs()) {
                    $skipped++;
                }
            }

            $list->splice($insertAt, 0, [$match]);
            $base = (int) $tournament->matches()->where('status', '!=', TennisMatch::PENDING)->max('queue_order');
            foreach ($list->values() as $i => $m) {
                $m->update(['queue_order' => $base + $i + 1]);
            }
        });

        $this->fillCourts($tournament);
    }

    /** Asigna manualmente un partido a una pista libre. */
    public function sendToCourt(TennisMatch $match, int $court): void
    {
        $tournament = $match->tournament;
        $occupied = $tournament->matches()->where('status', TennisMatch::PLAYING)->where('court', $court)->exists();

        if ($occupied || $match->status !== TennisMatch::PENDING || ! $match->hasBothPairs()) {
            throw ValidationException::withMessages(['court' => 'La pista no está libre o el partido no está listo.']);
        }

        $this->startOnCourt($match, $court);
    }

    private function startOnCourt(TennisMatch $match, int $court): void
    {
        $tournament = $match->tournament;

        $match->update([
            'status' => TennisMatch::PLAYING,
            'court' => $court,
            'started_at' => now(),
            'start_games' => (int) ($match->isGroup() ? $tournament->start_games_groups : $tournament->start_games_knockout),
        ]);
    }
}

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
    /* ------------------------------------------------------------------
     |  Inicio: sorteo de grupos y calendario
     * ------------------------------------------------------------------ */

    /** Se puede empezar aunque la estimación se pase del horario: decide quien organiza. */
    public function start(Tournament $tournament): void
    {
        $proposal = $tournament->proposal();
        $startGroups = $tournament->start_games_groups ?? $proposal['start_games'] ?? 0;
        $startKnockout = $tournament->start_games_knockout ?? $proposal['start_games_knockout'] ?? 0;

        if (! $tournament->isRegistration() || ! $proposal) {
            throw ValidationException::withMessages([
                'start' => 'No hay suficientes parejas para empezar.',
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

        $byGroup = [];
        foreach ($ordered->values() as $k => $pair) {
            $byGroup[$slots[$k]][] = $pair;
        }

        // Sorteo entre todas las parejas: último desempate cuando todo lo demás es igual.
        $drawPositions = $pairs->shuffle()->values()->mapWithKeys(fn (Pair $pair, int $index) => [$pair->id => $index + 1]);

        // Números seguidos por grupo (A: 1-4, B: 5-8...) y, dentro de cada grupo, al azar.
        $number = 0;
        ksort($byGroup);
        foreach ($byGroup as $groupIndex => $groupPairs) {
            foreach (collect($groupPairs)->shuffle() as $pair) {
                $pair->update([
                    'group_id' => $groups[$groupIndex]->id,
                    'number' => ++$number,
                    'draw_position' => $drawPositions[$pair->id],
                ]);
            }
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
            'withdrawn' => $pair->isWithdrawn(),
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

        // Las retiradas al final; el resto por victorias y, a igualdad, con los desempates.
        $ordered = collect($rows)
            ->groupBy(fn ($row) => ($row['withdrawn'] ? 1 : 0).'|'.str_pad((string) (999 - $row['won']), 3, '0', STR_PAD_LEFT))
            ->sortKeys()
            ->flatMap(fn (Collection $tied) => $this->breakTie($tied, $matches));

        return $ordered->values()->map(fn ($row, $i) => $row + ['position' => $i + 1]);
    }

    /**
     * Desempate entre parejas con las mismas victorias: entre dos, el partido que jugaron;
     * entre más, diferencia de juegos y juegos a favor, y si quedan dos igualadas, su partido.
     * Si todo coincide, decide el sorteo hecho al empezar el torneo.
     *
     * @param  Collection<int, array>  $tied
     * @param  Collection<int, TennisMatch>  $matches  partidos terminados del grupo
     * @return Collection<int, array>
     */
    private function breakTie(Collection $tied, Collection $matches): Collection
    {
        if ($tied->count() === 2) {
            return $this->headToHead($tied, $matches)
                ?? $tied->sortBy(fn ($row) => [-$row['diff'], -$row['gf'], $this->drawPosition($row['pair'])]);
        }

        return $tied->sortBy(fn ($row) => [-$row['diff'], -$row['gf']])
            ->groupBy(fn ($row) => $row['diff'].'|'.$row['gf'], preserveKeys: false)
            ->flatMap(fn (Collection $stillTied) => ($stillTied->count() === 2 ? $this->headToHead($stillTied, $matches) : null)
                ?? $stillTied->sortBy(fn ($row) => $this->drawPosition($row['pair'])));
    }

    /**
     * Las dos parejas ordenadas por el partido que jugaron entre ellas, o null si aún no lo han jugado.
     *
     * @param  Collection<int, array>  $two
     * @param  Collection<int, TennisMatch>  $matches
     */
    private function headToHead(Collection $two, Collection $matches): ?Collection
    {
        [$first, $second] = $two->values()->all();
        $ids = [$first['pair']->id, $second['pair']->id];
        $match = $matches->first(fn (TennisMatch $m) => in_array($m->pair1_id, $ids) && in_array($m->pair2_id, $ids));

        if (! $match) {
            return null;
        }

        return collect($match->winner_id === $first['pair']->id ? [$first, $second] : [$second, $first]);
    }

    /** Puesto en el sorteo; las parejas de antes de existir el sorteo, por orden de alta. */
    private function drawPosition(Pair $pair): int
    {
        return $pair->draw_position ?? $pair->id;
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
            ->whereNull('withdrawn_at')
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
     * Todas las parejas que siguen en el torneo ordenadas por puesto de grupo. Entre grupos
     * de distinto tamaño se compara por % de victorias y diferencia de juegos por partido.
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
                ->filter(fn ($row) => $row && ! $row['withdrawn'])
                ->sortByDesc(fn ($r) => [
                    $r['played'] ? $r['won'] / $r['played'] : 0,
                    $r['played'] ? $r['diff'] / $r['played'] : 0,
                    $r['played'] ? $r['gf'] / $r['played'] : 0,
                    -$this->drawPosition($r['pair']),
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
                'walkover' => false,
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

        $this->progress($tournament);
    }

    /**
     * Tras cualquier cambio en los partidos: genera la fase final y la consolación
     * cuando toca, cierra el torneo si no queda nada y ocupa las pistas libres.
     */
    private function progress(Tournament $tournament): void
    {
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
            $this->settleWithdrawnPairs($next);
        }
    }

    /* ------------------------------------------------------------------
     |  Imprevistos: W.O., retiradas, parejas tardías y pistas
     * ------------------------------------------------------------------ */

    /** Una pareja no se presenta o no puede acabar el partido: lo gana la otra. */
    public function walkover(TennisMatch $match, int $absentPairId): void
    {
        if ($match->isFinished() || ! $match->hasBothPairs() || ! in_array($absentPairId, [$match->pair1_id, $match->pair2_id])) {
            throw ValidationException::withMessages(['walkover' => 'Este partido no se puede dar por W.O.']);
        }

        DB::transaction(fn () => $this->settleWalkover($match, $absentPairId));

        $this->progress($match->tournament);
    }

    /**
     * La pareja se va del torneo: pierde por W.O. el partido que esté jugando y los pendientes,
     * y deja de contar para la fase final y la consolación. Sus resultados jugados se mantienen.
     */
    public function withdraw(Pair $pair): void
    {
        $tournament = $pair->tournament;

        if (! in_array($tournament->status, [Tournament::GROUPS, Tournament::KNOCKOUT])) {
            throw ValidationException::withMessages(['pair' => 'Solo se puede retirar una pareja con el torneo en juego.']);
        }
        if ($pair->isWithdrawn()) {
            return;
        }

        DB::transaction(function () use ($pair, $tournament) {
            $pair->update(['withdrawn_at' => now()]);

            $matches = $tournament->matches()
                ->whereIn('status', [TennisMatch::PENDING, TennisMatch::PLAYING])
                ->where(fn ($query) => $query->where('pair1_id', $pair->id)->orWhere('pair2_id', $pair->id))
                ->orderBy('queue_order')
                ->get();

            foreach ($matches as $match) {
                // Un W.O. anterior puede haber cerrado ya este partido en cascada.
                $match->refresh();

                // Si aún no tiene rival, el W.O. se da cuando llegue (ver advance).
                if (! $match->isFinished() && $match->hasBothPairs()) {
                    $this->settleWalkover($match, $pair->id);
                }
            }
        });

        $this->progress($tournament);
    }

    /** Deshace una retirada por error. Solo en grupos: después ya hay cuadro sorteado sin ella. */
    public function reinstate(Pair $pair): void
    {
        $tournament = $pair->tournament;

        if (! $pair->isWithdrawn()) {
            return;
        }
        if ($tournament->status !== Tournament::GROUPS) {
            throw ValidationException::withMessages(['pair' => 'Solo se puede reincorporar una pareja durante la fase de grupos.']);
        }

        DB::transaction(function () use ($pair, $tournament) {
            $tournament->matches()
                ->where('stage', 'group')
                ->where('walkover', true)
                ->where('finished_at', '>=', $pair->withdrawn_at)
                ->where('winner_id', '!=', $pair->id)
                ->where(fn ($query) => $query->where('pair1_id', $pair->id)->orWhere('pair2_id', $pair->id))
                ->get()
                ->each(fn (TennisMatch $match) => $match->update([
                    'status' => TennisMatch::PENDING,
                    'games1' => null,
                    'games2' => null,
                    'winner_id' => null,
                    'walkover' => false,
                    'court' => null,
                    'start_games' => null,
                    'started_at' => null,
                    'finished_at' => null,
                ]));

            $pair->update(['withdrawn_at' => null]);
        });

        $this->fillCourts($tournament);
    }

    /**
     * Pareja que llega con los grupos empezados: entra en el grupo con menos parejas y sus
     * partidos se reparten por la cola para que no los juegue todos seguidos.
     */
    public function addLatePair(Tournament $tournament, string $player1, string $player2): Pair
    {
        if ($tournament->status !== Tournament::GROUPS) {
            throw ValidationException::withMessages(['player1' => 'Solo se pueden añadir parejas durante la fase de grupos.']);
        }
        if ($tournament->pairs()->count() >= config('torneo.max_pairs')) {
            throw ValidationException::withMessages(['player1' => 'El máximo es de '.config('torneo.max_pairs').' parejas.']);
        }

        $pair = DB::transaction(function () use ($tournament, $player1, $player2) {
            $group = $tournament->groups()
                ->withCount(['pairs' => fn ($query) => $query->whereNull('withdrawn_at')])
                ->get()
                ->sortBy('pairs_count')
                ->first();

            $pair = $tournament->pairs()->create([
                'player1' => $player1,
                'player2' => $player2,
                'group_id' => $group->id,
                'number' => (int) $tournament->pairs()->max('number') + 1,
                'draw_position' => (int) $tournament->pairs()->max('draw_position') + 1,
            ]);
            $round = (int) $group->matches()->max('round') + 1;

            $newMatches = $group->pairs()->whereKeyNot($pair->id)->orderBy('id')->get()
                ->map(fn (Pair $opponent) => $tournament->matches()->make([
                    'stage' => 'group',
                    'group_id' => $group->id,
                    'round' => $round,
                    'pair1_id' => $opponent->id,
                    'pair2_id' => $pair->id,
                    'status' => TennisMatch::PENDING,
                ]))
                ->all();

            $queue = $tournament->matches()->where('status', TennisMatch::PENDING)->orderBy('queue_order')->get()->all();
            $spacing = max(2, intdiv(count($queue), max(1, count($newMatches))));
            $queueLength = count($queue);
            foreach (array_reverse($newMatches, true) as $index => $match) {
                array_splice($queue, min($queueLength, $index * $spacing), 0, [$match]);
            }

            $base = (int) $tournament->matches()->where('status', '!=', TennisMatch::PENDING)->max('queue_order');
            foreach ($queue as $index => $match) {
                $match->queue_order = $base + $index + 1;
                $match->save();
            }

            foreach ($newMatches as $match) {
                $this->settleWithdrawnPairs($match);
            }

            return $pair;
        });

        $this->progress($tournament);

        return $pair;
    }

    /** Pista que no se puede usar (lluvia, avería...): su partido vuelve el primero a la cola. */
    public function closeCourt(Tournament $tournament, int $court): void
    {
        abort_unless($court >= 1 && $court <= $tournament->courts, 422);

        DB::transaction(function () use ($tournament, $court) {
            $interrupted = $tournament->matches()->where('status', TennisMatch::PLAYING)->where('court', $court)->first();
            if ($interrupted) {
                $this->returnToQueue($interrupted);
            }

            $tournament->update(['closed_courts' => array_values(array_unique([...$tournament->closed_courts ?? [], $court]))]);

            // Las parejas ya estaban en pista: su partido entra en la primera que quede libre.
            if ($interrupted) {
                $first = (int) $tournament->matches()->where('status', TennisMatch::PENDING)->min('queue_order');
                $interrupted->update(['queue_order' => min($interrupted->queue_order, $first - 1)]);
            }
        });

        $this->fillCourts($tournament);
    }

    public function openCourt(Tournament $tournament, int $court): void
    {
        $tournament->update(['closed_courts' => array_values(array_diff($tournament->closed_courts ?? [], [$court]))]);

        $this->fillCourts($tournament);
    }

    /** Cambia el número de pistas con el torneo en juego. Los partidos de las pistas que sobran vuelven a la cola. */
    public function changeCourts(Tournament $tournament, int $courts): void
    {
        abort_unless($courts >= 1 && $courts <= config('torneo.max_courts'), 422);

        DB::transaction(function () use ($tournament, $courts) {
            $tournament->matches()->where('status', TennisMatch::PLAYING)->where('court', '>', $courts)
                ->get()->each(fn (TennisMatch $match) => $this->returnToQueue($match));

            $tournament->update([
                'courts' => $courts,
                'closed_courts' => array_values(array_filter($tournament->closed_courts ?? [], fn (int $court) => $court <= $courts)),
            ]);
        });

        $this->fillCourts($tournament);
    }

    /** Partido que se estaba jugando y vuelve a la cola sin perder su sitio. */
    private function returnToQueue(TennisMatch $match): void
    {
        $match->update([
            'status' => TennisMatch::PENDING,
            'court' => null,
            'started_at' => null,
            'start_games' => null,
        ]);
    }

    /**
     * W.O.: 6 juegos contra el marcador inicial +1 (6-1 empezando 0-0), para no dar
     * tanta diferencia de juegos sin jugar. Empezando 4-4 sería 6-5, que no existe: 7-5.
     */
    private function settleWalkover(TennisMatch $match, int $absentPairId): void
    {
        $start = $match->start_games ?? $match->startGames();
        $winner = $absentPairId === $match->pair1_id ? $match->pair2_id : $match->pair1_id;
        [$winnerGames, $loserGames] = self::walkoverScore($start);

        $match->update([
            'games1' => $winner === $match->pair1_id ? $winnerGames : $loserGames,
            'games2' => $winner === $match->pair2_id ? $winnerGames : $loserGames,
            'winner_id' => $winner,
            'status' => TennisMatch::FINISHED,
            'walkover' => true,
            'start_games' => $start,
            'finished_at' => now(),
        ]);

        if (! $match->isGroup()) {
            $this->advance($match);
        }
    }

    /** @return array{int, int} juegos del ganador y del perdedor de un W.O. */
    public static function walkoverScore(int $startGames): array
    {
        $loserGames = $startGames + 1;

        return [$loserGames >= 5 ? 7 : 6, $loserGames];
    }

    /** Si a un partido le llega una pareja retirada, lo gana el rival sin jugar. */
    private function settleWithdrawnPairs(TennisMatch $match): void
    {
        if ($match->status !== TennisMatch::PENDING || ! $match->hasBothPairs()) {
            return;
        }

        $withdrawnId = Pair::whereKey([$match->pair1_id, $match->pair2_id])->whereNotNull('withdrawn_at')->value('id');

        if ($withdrawnId) {
            $this->settleWalkover($match, $withdrawnId);
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
        $freeCourts = array_diff($tournament->openCourts(), $playing->pluck('court')->all());

        foreach ($freeCourts as $court) {
            $busy = $this->busyPairIds($tournament);
            $ready = $this->upcoming($tournament)->filter(fn ($m) => $this->isReady($m, $busy));

            // Los siguientes no tienen pista fija: entra el primero de la cola que pueda jugar.
            $next = $ready->first();

            if ($next) {
                $this->startOnCourt($next, $court);
            }
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

        if ($occupied || $tournament->isCourtClosed($court) || $court > $tournament->courts
            || $match->status !== TennisMatch::PENDING || ! $match->hasBothPairs()) {
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

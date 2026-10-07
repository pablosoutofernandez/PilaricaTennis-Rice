<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Decide el formato de un torneo de un día según el número de parejas,
 * el tiempo disponible y las pistas.
 *
 * Prueba cuadros principales sin byes (2, 4, 8, 16 parejas): primero que todas las
 * parejas lleguen al objetivo de partidos, después el que más partidos reparte y,
 * a igualdad, el marcador inicial más bajo que cabe en la jornada. Si sobra tiempo,
 * la fase final se juega con un marcador más largo que los grupos.
 *
 * Entre los dos modos de consolación se recomienda el que lleva a todas al objetivo
 * de partidos y, a igualdad, el que más minutos de juego asegura a la que menos juega.
 */
class FormatPlanner
{
    /**
     * @return array{pairs: int, group_sizes: int[], qualifiers: int, consolation_pairs: int, qualification: string, consolation_description: string, start_games: int, start_games_knockout: int, group_matches: int, knockout_matches: int, consolation_matches: int, total_matches: int, min_matches_per_pair: int, turns: int, group_turns: int, elimination_turns: int, estimated_minutes: int, fits: bool, recommended_consolation_all: ?bool, warning: ?string}
     */
    public function plan(int $pairs, int $minutesAvailable, int $courts = 2, bool $consolationAll = false): array
    {
        if ($pairs < config('torneo.min_pairs')) {
            throw new InvalidArgumentException('Se necesitan al menos '.config('torneo.min_pairs').' parejas.');
        }
        if ($pairs > config('torneo.max_pairs')) {
            throw new InvalidArgumentException('El máximo es de '.config('torneo.max_pairs').' parejas.');
        }

        $courts = min(config('torneo.max_courts'), max(1, $courts));
        $groupSizes = $this->groupSizes($pairs);

        $result = $this->bestFormat($groupSizes, $pairs, $minutesAvailable, $courts, $consolationAll);
        $alternative = $this->bestFormat($groupSizes, $pairs, $minutesAvailable, $courts, ! $consolationAll);

        $comparison = $this->recommendationScore($result) <=> $this->recommendationScore($alternative);
        $result['recommended_consolation_all'] = match ($comparison) {
            1 => $consolationAll,
            -1 => ! $consolationAll,
            default => null,
        };
        $result['warning'] = $this->warning($result['fits'], $result['start_games']);
        unset($result['score'], $result['guaranteed_minutes']);

        return $result;
    }

    /** Elige el mejor cuadro principal para un modo de consolación. */
    private function bestFormat(array $groupSizes, int $pairs, int $minutesAvailable, int $courts, bool $consolationAll): array
    {
        $options = array_map(
            fn (int $qualifiers) => $this->evaluate($groupSizes, $pairs, $qualifiers, $minutesAvailable, $courts, $consolationAll),
            $this->qualifierOptions($groupSizes, $pairs, $consolationAll),
        );

        usort($options, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $options[0];
    }

    private function evaluate(array $groupSizes, int $pairs, int $qualifiers, int $minutesAvailable, int $courts, bool $consolationAll): array
    {
        $minutesTable = config('torneo.match_minutes');
        $maxPreferred = config('torneo.max_preferred_start');

        $groupMatches = (int) array_sum(array_map(fn ($size) => $size * ($size - 1) / 2, $groupSizes));
        $groupTurns = $this->groupTurns($groupSizes, $courts);
        $consolationPairs = $consolationAll ? $pairs - $qualifiers : intdiv($qualifiers, 2);
        $eliminationTurns = $this->eliminationTurns($qualifiers, $consolationPairs, $consolationAll, $courts);
        $minMatchesPerPair = $this->minMatchesPerPair($groupSizes, $consolationPairs, $consolationAll);

        $fitsWith = function (int $maxStart) use ($minutesTable, $groupTurns, $eliminationTurns, $minutesAvailable): ?int {
            foreach (array_keys($minutesTable) as $start) {
                if ($start <= $maxStart && $this->minutes($groupTurns, $eliminationTurns, $start, $start) <= $minutesAvailable) {
                    return $start;
                }
            }

            return null;
        };

        $preferredStart = $fitsWith($maxPreferred);
        $start = $preferredStart ?? $fitsWith(array_key_last($minutesTable));
        $fitLevel = match (true) {
            $preferredStart !== null => 2,
            $start !== null => 1,
            default => 0,
        };
        $start ??= array_key_last($minutesTable);

        $startKnockout = $start;
        while ($fitLevel > 0 && $startKnockout > 0
            && $this->minutes($groupTurns, $eliminationTurns, $start, $startKnockout - 1) <= $minutesAvailable) {
            $startKnockout--;
        }

        $knockoutMatches = $qualifiers - 1;
        $consolationMatches = max(0, $consolationPairs - 1);
        $totalMatches = $groupMatches + $knockoutMatches + $consolationMatches;
        $minGroupMatches = min($groupSizes) - 1;
        $guaranteedMinutes = $minGroupMatches * $minutesTable[$start]
            + ($minMatchesPerPair - $minGroupMatches) * $minutesTable[$startKnockout];

        return [
            'pairs' => $pairs,
            'group_sizes' => $groupSizes,
            'qualifiers' => $qualifiers,
            'consolation_pairs' => $consolationPairs,
            'qualification' => $this->describeQualification($groupSizes, $qualifiers),
            'consolation_description' => $this->describeConsolation($qualifiers, $consolationPairs, $consolationAll),
            'start_games' => $start,
            'start_games_knockout' => $startKnockout,
            'group_matches' => $groupMatches,
            'knockout_matches' => $knockoutMatches,
            'consolation_matches' => $consolationMatches,
            'total_matches' => $totalMatches,
            'min_matches_per_pair' => $minMatchesPerPair,
            'turns' => $groupTurns + $eliminationTurns,
            'group_turns' => $groupTurns,
            'elimination_turns' => $eliminationTurns,
            'estimated_minutes' => $this->minutes($groupTurns, $eliminationTurns, $start, $startKnockout),
            'fits' => $fitLevel > 0,
            'score' => [
                $fitLevel,
                $fitLevel > 0 ? min($minMatchesPerPair, config('torneo.target_matches_per_pair')) : -($groupTurns + $eliminationTurns),
                $totalMatches,
                -$start,
                -$startKnockout,
                $qualifiers,
            ],
            'guaranteed_minutes' => $guaranteedMinutes,
        ];
    }

    /**
     * Lo que decide qué modo de consolación se recomienda: que quepa, que todas lleguen
     * al objetivo de partidos y los minutos de juego que tiene asegurados la pareja que menos juega.
     *
     * @return int[]
     */
    private function recommendationScore(array $plan): array
    {
        return [$plan['score'][0], $plan['score'][1], $plan['guaranteed_minutes'], $plan['total_matches']];
    }

    /**
     * Partidos que juega como mínimo cualquier pareja: las eliminadas en grupos solo
     * suman la consolación si es para todos.
     */
    private function minMatchesPerPair(array $groupSizes, int $consolationPairs, bool $consolationAll): int
    {
        return min($groupSizes) - 1 + (int) ($consolationAll && $consolationPairs >= 2);
    }

    /**
     * Minutos totales: partidos de cada fase y cambio de pista entre tandas, con el margen
     * de organización y, en jornadas largas, los huecos con pistas vacías mientras la gente se turna para comer.
     */
    private function minutes(int $groupTurns, int $eliminationTurns, int $startGroups, int $startKnockout): int
    {
        $minutes = config('torneo.match_minutes');
        $turns = $groupTurns + $eliminationTurns;

        $playing = $groupTurns * $minutes[$startGroups]
            + $eliminationTurns * $minutes[$startKnockout]
            + max(0, $turns - 1) * config('torneo.changeover_minutes');
        $total = (int) ceil(round($playing * (1 + config('torneo.organization_margin')), 2));

        if ($total > config('torneo.lunch_break_after_minutes')) {
            $total += config('torneo.lunch_break_minutes');
        }

        return $total;
    }

    /** Cuenta las tandas de cada ronda para no estimar partidos de un grupo en paralelo. */
    private function groupTurns(array $groupSizes, int $courts): int
    {
        $rounds = max(array_map(fn ($size) => $size % 2 === 0 ? $size - 1 : $size, $groupSizes));
        $turns = 0;

        for ($round = 0; $round < $rounds; $round++) {
            $matches = array_sum(array_map(
                fn ($size) => $round < ($size % 2 === 0 ? $size - 1 : $size) ? intdiv($size, 2) : 0,
                $groupSizes,
            ));
            $turns += (int) ceil($matches / $courts);
        }

        return $turns;
    }

    /**
     * Tandas de la fase final con el cuadro principal y el de consolación compartiendo pistas.
     * La consolación solo para el cuadro empieza una ronda más tarde, cuando hay perdedores.
     */
    private function eliminationTurns(int $qualifiers, int $consolationPairs, bool $consolationAll, int $courts): int
    {
        $knockoutRounds = $this->knockoutRoundMatchCounts($qualifiers);
        $consolationRounds = $this->knockoutRoundMatchCounts($consolationPairs);
        $offset = $consolationAll ? 0 : 1;
        $stages = max(count($knockoutRounds), count($consolationRounds) + $offset);
        $turns = 0;

        for ($stage = 0; $stage < $stages; $stage++) {
            $matches = ($knockoutRounds[$stage] ?? 0) + ($consolationRounds[$stage - $offset] ?? 0);
            $turns += (int) ceil($matches / $courts);
        }

        return $turns;
    }

    /** @return int[] tamaños de grupo, de mayor a menor */
    public function groupSizes(int $pairs): array
    {
        if ($pairs <= 7) {
            return [$pairs];
        }

        if ($pairs === 11) {
            return [6, 5];
        }

        $groupCount = (int) ceil($pairs / 5);
        $groupSize = intdiv($pairs, $groupCount);
        $largerGroups = $pairs % $groupCount;

        return array_merge(
            array_fill(0, $largerGroups, $groupSize + 1),
            array_fill(0, $groupCount - $largerGroups, $groupSize),
        );
    }

    /**
     * Cuadros principales sin byes en los que cada grupo deja fuera al menos a una pareja,
     * para que la fase de grupos sea competitiva. Con consolación para todos tienen que
     * quedar al menos dos parejas fuera. Solo con 4 o 5 parejas se va directo a la final.
     *
     * @return int[] de menor a mayor
     */
    public function qualifierOptions(array $groupSizes, int $pairs, bool $consolationAll): array
    {
        $groups = count($groupSizes);
        $options = [];
        $smallest = $pairs < 6 ? 2 : 4;

        for ($qualifiers = $smallest; $qualifiers <= ($consolationAll ? $pairs - 2 : $pairs - 1); $qualifiers *= 2) {
            $mostPerGroup = intdiv($qualifiers, $groups) + (int) ($qualifiers % $groups > 0);

            if ($mostPerGroup < min($groupSizes)) {
                $options[] = $qualifiers;
            }
        }

        return $options;
    }

    /** @return int[] partidos reales por ronda, teniendo en cuenta los byes */
    private function knockoutRoundMatchCounts(int $pairs): array
    {
        if ($pairs < 2) {
            return [];
        }

        $bracketSize = 2;
        while ($bracketSize < $pairs) {
            $bracketSize *= 2;
        }

        $slots = array_map(fn (int $seed) => $seed <= $pairs, $this->bracketOrder($bracketSize));

        $roundMatches = [];
        for ($roundSize = $bracketSize; $roundSize >= 2; $roundSize /= 2) {
            $nextSlots = [];
            $matches = 0;

            for ($index = 0; $index < $roundSize; $index += 2) {
                $left = $slots[$index];
                $right = $slots[$index + 1];
                $matches += (int) ($left && $right);
                $nextSlots[] = $left || $right;
            }

            $roundMatches[] = $matches;
            $slots = $nextSlots;
        }

        return $roundMatches;
    }

    /** @return int[] */
    private function bracketOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $nextSize = count($order) * 2;
            $order = collect($order)->flatMap(fn ($seed) => [$seed, $nextSize + 1 - $seed])->all();
        }

        return $order;
    }

    public function describeQualification(array $groupSizes, int $qualifiers): string
    {
        $groups = count($groupSizes);
        $perGroup = intdiv($qualifiers, $groups);
        $extra = $qualifiers % $groups;

        if ($groups === 1) {
            return "Pasan los {$qualifiers} primeros";
        }

        $text = match ($perGroup) {
            1 => 'Pasa el 1º de cada grupo',
            default => 'Pasan los '.$perGroup.' primeros de cada grupo',
        };

        if ($extra > 0) {
            $position = $perGroup + 1;
            $text .= " + {$extra} mejor".($extra > 1 ? 'es' : '')." {$position}º";
        }

        return $text;
    }

    /** Quién juega la consolación y con qué cuadro. */
    public function describeConsolation(int $qualifiers, int $consolationPairs, bool $consolationAll): string
    {
        if ($consolationPairs < 2) {
            return 'Sin consolación.';
        }

        $who = $consolationAll
            ? "Las {$consolationPairs} parejas eliminadas en grupos"
            : "Las {$consolationPairs} parejas que pierden en ".mb_strtolower(self::roundName($qualifiers));

        $bracketSize = 2;
        while ($bracketSize < $consolationPairs) {
            $bracketSize *= 2;
        }
        $byes = $bracketSize - $consolationPairs;

        $format = $consolationPairs === 2
            ? ' se juegan la final de consolación'
            : ' juegan un cuadro aparte desde '.mb_strtolower(self::roundName($bracketSize));

        if ($byes > 0) {
            $format .= $byes === 1
                ? ' (la mejor clasificada pasa directa a la siguiente ronda)'
                : " (las {$byes} mejor clasificadas pasan directas a la siguiente ronda)";
        }

        $rest = $consolationAll ? '' : ' Las eliminadas en grupos no siguen jugando.';

        return $who.$format.'.'.$rest;
    }

    /** Minutos estimados con marcadores iniciales distintos para grupos y fase final. */
    public function estimate(array $plan, int $startGroups, int $startKnockout): int
    {
        return $this->minutes($plan['group_turns'], $plan['elimination_turns'], $startGroups, $startKnockout);
    }

    public static function roundName(int $pairsInRound): string
    {
        return match ($pairsInRound) {
            2 => 'Final',
            3 => 'Semifinales',
            4 => 'Semifinales',
            6 => 'Ronda previa',
            8 => 'Cuartos de final',
            16 => 'Octavos de final',
            32 => 'Dieciseisavos',
            default => "Ronda de {$pairsInRound}",
        };
    }

    public static function startLabel(int $games): string
    {
        return "{$games}-{$games}";
    }

    private function warning(bool $fits, int $start): ?string
    {
        if (! $fits) {
            return 'No da tiempo ni empezando 4-4. Amplía el horario o reduce el número de parejas.';
        }

        if ($start > config('torneo.max_preferred_start')) {
            return 'Sets muy cortos: si es posible, amplía el horario.';
        }

        return null;
    }
}

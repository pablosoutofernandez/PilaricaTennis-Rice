<?php

namespace Tests\Feature;

use App\Livewire\Bracket;
use App\Livewire\Pairs;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\FormatPlanner;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentSimulationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTournament(int $pairs): Tournament
    {
        $t = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);
        for ($i = 1; $i <= $pairs; $i++) {
            $t->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}", 'seeded' => $i <= 2]);
        }

        return $t;
    }

    private function randomScore(int $start): array
    {
        $options = [[6, max($start, 0)], [6, 3], [6, 4], [7, 5], [7, 6]];
        $options = array_values(array_filter($options, fn ($o) => $o[1] >= $start));
        $s = $options[array_rand($options)];

        return random_int(0, 1) ? $s : [$s[1], $s[0]];
    }

    public static function sizes(): array
    {
        return collect(range(4, 16))
            ->flatMap(fn ($n) => ["{$n} first-round losers" => [$n, false], "{$n} group eliminations" => [$n, true]])
            ->all();
    }

    /**
     * Formato elegido con 11 horas y 2 pistas.
     *
     * @return array<string, array{int, bool, int[], int, int, int, int, int, int, int, int, bool}>
     */
    public static function proposalRows(): array
    {
        // parejas, consolación para todos, grupos, clasificados, parejas en consolación,
        // set grupos, set cuadros, partidos, tandas, minutos, mínimo por pareja, modo recomendado
        return [
            '4 first-round losers' => [4, false, [4], 2, 1, 0, 0, 7, 4, 193, 3, false],
            '5 first-round losers' => [5, false, [5], 4, 2, 0, 0, 14, 7, 371, 4, false],
            '6 first-round losers' => [6, false, [6], 4, 2, 0, 0, 19, 12, 619, 5, false],
            '7 first-round losers' => [7, false, [7], 4, 2, 2, 0, 25, 16, 601, 6, false],
            '8 first-round losers' => [8, false, [4, 4], 4, 2, 0, 0, 16, 8, 421, 3, false],
            '9 first-round losers' => [9, false, [5, 4], 4, 2, 0, 0, 20, 10, 520, 3, false],
            '10 first-round losers' => [10, false, [5, 5], 8, 4, 1, 1, 30, 15, 652, 4, false],
            '11 first-round losers' => [11, false, [6, 5], 8, 4, 3, 1, 35, 20, 630, 4, false],
            '12 first-round losers' => [12, false, [4, 4, 4], 8, 4, 1, 0, 28, 14, 649, 3, false],
            '13 first-round losers' => [13, false, [5, 4, 4], 8, 4, 2, 0, 32, 16, 648, 3, false],
            '14 first-round losers' => [14, false, [5, 5, 4], 8, 4, 2, 2, 36, 18, 639, 3, false],
            '15 first-round losers' => [15, false, [5, 5, 5], 8, 4, 3, 1, 40, 20, 630, 4, false],
            '16 first-round losers' => [16, false, [4, 4, 4, 4], 8, 4, 2, 1, 34, 17, 643, 3, false],
            '4 group eliminations' => [4, true, [4], 2, 2, 0, 0, 8, 4, 193, 4, true],
            '5 group eliminations' => [5, true, [5], 2, 3, 0, 0, 13, 7, 371, 5, true],
            '6 group eliminations' => [6, true, [6], 4, 2, 1, 0, 19, 13, 591, 6, true],
            '7 group eliminations' => [7, true, [7], 4, 3, 2, 0, 26, 17, 651, 7, true],
            '8 group eliminations' => [8, true, [4, 4], 4, 4, 0, 0, 18, 9, 470, 4, true],
            '9 group eliminations' => [9, true, [5, 4], 4, 5, 1, 0, 23, 13, 607, 4, true],
            '10 group eliminations' => [10, true, [5, 5], 8, 2, 1, 1, 28, 15, 652, 5, true],
            '11 group eliminations' => [11, true, [6, 5], 8, 3, 3, 2, 34, 21, 626, 5, true],
            '12 group eliminations' => [12, true, [4, 4, 4], 8, 4, 1, 1, 28, 15, 652, 4, true],
            '13 group eliminations' => [13, true, [5, 4, 4], 8, 5, 2, 1, 33, 17, 651, 4, true],
            '14 group eliminations' => [14, true, [5, 5, 4], 8, 6, 3, 1, 38, 19, 619, 4, true],
            '15 group eliminations' => [15, true, [5, 5, 5], 8, 7, 3, 2, 43, 22, 660, 5, true],
            '16 group eliminations' => [16, true, [4, 4, 4, 4], 8, 8, 3, 1, 38, 19, 634, 4, true],
        ];
    }

    #[DataProvider('proposalRows')]
    public function test_every_format_row_matches_its_group_draw_and_schedule(
        int $pairs,
        bool $consolationAll,
        array $groupSizes,
        int $qualifiers,
        int $consolationPairs,
        int $startGroups,
        int $startKnockout,
        int $totalMatches,
        int $turns,
        int $estimatedMinutes,
        int $minMatchesPerPair,
        bool $isRecommendedMode,
    ): void {
        $planner = app(FormatPlanner::class);
        $plan = $planner->plan($pairs, 11 * 60, 2, $consolationAll);

        $this->assertSame($groupSizes, $plan['group_sizes']);
        $this->assertLessThan($pairs, $plan['qualifiers'], 'Siempre queda alguna pareja fuera en grupos');
        $this->assertSame($qualifiers, $plan['qualifiers']);
        $this->assertSame($qualifiers - 1, $plan['knockout_matches']);
        $this->assertSame($consolationPairs, $plan['consolation_pairs']);
        $this->assertSame(max(0, $consolationPairs - 1), $plan['consolation_matches']);
        $this->assertSame($totalMatches, $plan['total_matches']);
        $this->assertSame($turns, $plan['turns']);
        $this->assertSame($startGroups, $plan['start_games']);
        $this->assertSame($startKnockout, $plan['start_games_knockout']);
        $this->assertSame($estimatedMinutes, $plan['estimated_minutes']);
        $this->assertSame($planner->estimate($plan, $startGroups, $startKnockout), $plan['estimated_minutes']);
        $this->assertSame($minMatchesPerPair, $plan['min_matches_per_pair']);
        $this->assertSame($isRecommendedMode ? $consolationAll : ! $consolationAll, $plan['recommended_consolation_all']);
        $this->assertTrue($plan['fits']);
        $this->assertNull($plan['warning']);
    }

    public function test_group_sizes_match_the_tournament_examples(): void
    {
        $planner = app(FormatPlanner::class);

        $this->assertSame([4, 4], $planner->groupSizes(8));
        $this->assertSame([5, 5], $planner->groupSizes(10));
        $this->assertSame([6, 5], $planner->groupSizes(11));
        $this->assertSame([4, 4, 4, 4], $planner->groupSizes(16));
    }

    public function test_format_planner_rejects_more_than_sixteen_pairs(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(FormatPlanner::class)->plan(17, 11 * 60, 2);
    }

    public function test_pair_registration_stops_at_sixteen_pairs(): void
    {
        $tournament = $this->makeTournament(16);

        Livewire::test(Pairs::class, ['tournament' => $tournament])
            ->set('player1', 'Jugador 17')
            ->set('player2', 'Jugador 18')
            ->call('add')
            ->assertHasErrors('player1');

        $this->assertSame(16, $tournament->pairs()->count());
    }

    public function test_consolation_checkbox_updates_the_schedule(): void
    {
        $tournament = $this->makeTournament(8);
        $this->assertSame(4, $tournament->proposal()['qualifiers']);
        $this->assertSame(2, $tournament->proposal()['consolation_pairs']);

        Livewire::test(Pairs::class, ['tournament' => $tournament])
            ->set('consolationAll', true)
            ->assertSet('consolationAll', true)
            ->assertSee('★ Recomendado para 8 parejas: marcado');

        $tournament->refresh();
        $plan = $tournament->proposal();

        $this->assertTrue($tournament->consolation_all);
        $this->assertSame(4, $plan['qualifiers']);
        $this->assertSame(4, $plan['consolation_pairs']);
        $this->assertSame(3, $plan['consolation_matches']);
    }

    public function test_tournament_cannot_start_with_markers_that_exceed_the_available_time(): void
    {
        $tournament = $this->makeTournament(16);
        $tournament->update(['start_games_groups' => 0, 'start_games_knockout' => 0]);

        $this->expectException(ValidationException::class);

        app(TournamentManager::class)->start($tournament);
    }

    public function test_all_group_and_tournament_losers_receive_a_second_chance_when_selected(): void
    {
        $tournament = $this->makeTournament(8);
        $tournament->update(['consolation_all' => true]);
        $manager = app(TournamentManager::class);
        $manager->start($tournament);

        while ($tournament->fresh()->status === Tournament::GROUPS) {
            $match = $tournament->matches()->where('status', TennisMatch::PLAYING)->first();
            [$games1, $games2] = $this->randomScore($match->startGames());
            $manager->recordResult($match, $games1, $games2);
        }

        while ($tournament->matches()->where('stage', 'knockout')->where('status', '!=', TennisMatch::FINISHED)->exists()) {
            $match = $tournament->matches()->where('stage', 'knockout')->where('status', TennisMatch::PLAYING)->first();
            [$games1, $games2] = $this->randomScore($match->startGames());
            $manager->recordResult($match, $games1, $games2);
        }

        while ($tournament->fresh()->status !== Tournament::FINISHED) {
            $match = $tournament->matches()->where('stage', 'consolation')->where('status', TennisMatch::PLAYING)->first();
            [$games1, $games2] = $this->randomScore($match->startGames());
            $manager->recordResult($match, $games1, $games2);
        }

        $this->assertSame(3, $tournament->matches()->where('stage', 'consolation')->count());
        $pairIds = $tournament->matches()->where('stage', 'consolation')->get()
            ->flatMap(fn ($match) => [$match->pair1_id, $match->pair2_id])->filter()->unique();
        $this->assertCount(4, $pairIds);
        $mainChampionId = $tournament->matches()->where('stage', 'knockout')
            ->where('bracket_size', 2)->where('position', 1)->value('winner_id');
        $this->assertNotContains($mainChampionId, $pairIds);
    }

    public function test_ten_pairs_qualify_the_top_four_of_each_group(): void
    {
        $planner = app(FormatPlanner::class);

        foreach ([false, true] as $consolationAll) {
            $plan = $planner->plan(10, 11 * 60, 2, $consolationAll);

            $this->assertSame(8, $plan['qualifiers']);
            $this->assertSame('Pasan los 4 primeros de cada grupo', $plan['qualification']);
        }
    }

    public function test_estimate_adds_changeover_organization_margin_and_lunch(): void
    {
        $planner = app(FormatPlanner::class);
        $plan = $planner->plan(4, 11 * 60, 2);

        $this->assertSame(4, $plan['turns']);
        $this->assertSame((int) ceil((4 * 40 + 3 * 5) * 1.1), $planner->estimate($plan, 0, 0));

        config(['torneo.changeover_minutes' => 10]);

        $this->assertSame(209, $planner->estimate($plan, 0, 0));

        config(['torneo.lunch_break_after_minutes' => 200]);

        $this->assertSame(209 + 30, $planner->estimate($plan, 0, 0));
    }

    public function test_short_schedules_fall_back_to_shorter_sets(): void
    {
        $planner = app(FormatPlanner::class);
        $longDay = $planner->plan(16, 11 * 60, 2);
        $shortDay = $planner->plan(16, 8 * 60, 2);

        $this->assertGreaterThan($longDay['start_games'], $shortDay['start_games']);
        $this->assertLessThanOrEqual(8 * 60, $shortDay['estimated_minutes']);
        $this->assertTrue($shortDay['fits']);
    }

    public function test_semifinals_cross_first_with_fourth_and_second_with_third(): void
    {
        $tournament = $this->makeTournament(6);
        $manager = app(TournamentManager::class);
        $manager->start($tournament);

        while ($tournament->fresh()->status === Tournament::GROUPS) {
            $match = $tournament->matches()->where('status', TennisMatch::PLAYING)->first();
            [$games1, $games2] = $this->randomScore($match->startGames());
            $manager->recordResult($match, $games1, $games2);
        }

        $positions = $manager->standings($tournament->groups()->sole())
            ->mapWithKeys(fn ($row) => [$row['pair']->id => $row['position']]);
        $semifinals = $tournament->matches()->where('stage', 'knockout')->where('bracket_size', 4)->orderBy('position')->get()
            ->map(fn ($match) => collect([$positions[$match->pair1_id], $positions[$match->pair2_id]])->sort()->values()->all())
            ->all();

        $this->assertSame([[1, 4], [2, 3]], $semifinals);
    }

    public function test_consolation_byes_go_to_the_best_ranked_group_eliminations(): void
    {
        $tournament = $this->makeTournament(9);
        $tournament->update(['consolation_all' => true]);
        $manager = app(TournamentManager::class);
        $manager->start($tournament);

        while ($tournament->fresh()->status === Tournament::GROUPS) {
            $match = $tournament->matches()->where('status', TennisMatch::PLAYING)->first();
            [$games1, $games2] = $this->randomScore($match->startGames());
            $manager->recordResult($match, $games1, $games2);
        }

        $positions = [];
        foreach ($tournament->groups as $group) {
            foreach ($manager->standings($group) as $row) {
                $positions[$row['pair']->id] = $row['position'];
            }
        }
        $firstRound = $tournament->matches()->where('stage', 'consolation')->where('bracket_size', 8)->sole();

        $this->assertEqualsCanonicalizing([4, 5], [$positions[$firstRound->pair1_id], $positions[$firstRound->pair2_id]]);
    }

    #[DataProvider('sizes')]
    public function test_full_tournament_runs_to_the_end(int $pairs, bool $consolationAll): void
    {
        $manager = app(TournamentManager::class);
        $t = $this->makeTournament($pairs);
        $t->update(['consolation_all' => $consolationAll]);
        $manager->start($t);
        $t->refresh();

        $this->assertSame(Tournament::GROUPS, $t->status);
        $this->assertSame(array_sum($t->group_sizes), $pairs);
        $this->assertSame(min($t->courts, $t->matches()->count()), $t->matches()->where('status', 'playing')->count());

        $guard = 0;
        while ($t->status !== Tournament::FINISHED && $guard++ < 500) {
            $playing = $t->matches()->where('status', TennisMatch::PLAYING)->get();
            $this->assertNotEmpty($playing, "Atasco con {$pairs} parejas en estado {$t->status}");

            // Nunca una pareja en dos pistas a la vez
            $ids = $playing->flatMap(fn ($m) => [$m->pair1_id, $m->pair2_id]);
            $this->assertSame($ids->count(), $ids->unique()->count());

            $match = $playing->first();
            [$g1, $g2] = $this->randomScore($match->startGames());
            $manager->recordResult($match, $g1, $g2);

            if ($t->fresh()->status === Tournament::KNOCKOUT && ! isset($checked)) {
                $checked = true;
                $this->assertKnockoutIsSound($t->fresh());
            }
            $t->refresh();
        }

        $this->assertSame(Tournament::FINISHED, $t->status);
        $final = $t->matches()->where('bracket_size', 2)->where('third_place', false)->first();
        $this->assertNotNull($final->winner_id);
        $plan = $t->proposal();
        $this->assertSame($plan['total_matches'], $t->matches()->count());
        $this->assertSame($plan['knockout_matches'], $t->matches()->where('stage', 'knockout')->count());
        $this->assertSame($plan['consolation_matches'], $t->matches()->where('stage', 'consolation')->count());
        $consolationFinal = $t->matches()->where('stage', 'consolation')->where('bracket_size', 2)->first();
        if ($consolationFinal) {
            $this->assertNotNull($consolationFinal->winner_id);
            $consolationWinner = $consolationFinal->winner_id === $consolationFinal->pair1_id
                ? $consolationFinal->pair1
                : $consolationFinal->pair2;
        } else {
            $loser = $t->matches()->where('stage', 'knockout')->where('round', 1)->first();
            $loserId = $loser->winner_id === $loser->pair1_id ? $loser->pair2_id : $loser->pair1_id;
            $consolationWinner = $loser->pair1_id === $loserId ? $loser->pair1 : $loser->pair2;
        }

        Livewire::test(Bracket::class, ['tournament' => $t])
            ->assertSee('Cuadro principal')
            ->assertSee('Cuadro de consolación')
            ->assertSee('Campeón de consolación')
            ->assertSee($consolationWinner->name);
    }

    private function assertKnockoutIsSound(Tournament $t): void
    {
        $bracketSize = 2;
        while ($bracketSize < $t->qualifiers) {
            $bracketSize *= 2;
        }
        $first = $t->matches()->where('stage', 'knockout')->where('bracket_size', $bracketSize)->get();
        $this->assertNotEmpty($first);

        $pairIds = $t->matches()->where('stage', 'knockout')->get()->flatMap(fn ($m) => [$m->pair1_id, $m->pair2_id]);
        $this->assertSame($t->qualifiers, $pairIds->filter()->unique()->count());

        if ($t->groups()->count() > 1) {
            foreach ($first as $m) {
                $this->assertNotSame($m->pair1->group_id, $m->pair2->group_id, 'Cruce del mismo grupo en primera ronda');
            }
        }

        // Todos los campeones de grupo están clasificados
        $manager = app(TournamentManager::class);
        foreach ($t->groups as $group) {
            $this->assertContains($manager->standings($group)->first()['pair']->id, $pairIds);
        }
    }

    public function test_score_validation(): void
    {
        $m = app(TournamentManager::class);
        $this->assertNull($m->validateScore(6, 4, 0));
        $this->assertNull($m->validateScore(6, 7, 3));
        $this->assertNull($m->validateScore(5, 7, 2));
        $this->assertNotNull($m->validateScore(6, 5, 0));
        $this->assertNotNull($m->validateScore(6, 1, 2));
        $this->assertNotNull($m->validateScore(4, 4, 0));
        $this->assertNotNull($m->validateScore(8, 6, 0));
    }

    public function test_postpone_moves_match_back_and_frees_court(): void
    {
        $manager = app(TournamentManager::class);
        $t = $this->makeTournament(8);
        $manager->start($t);

        $next = $manager->upcoming($t)->first();
        $manager->postpone($next);
        $this->assertNotSame($next->id, $manager->upcoming($t)->first()->id);
        $this->assertSame(1, $next->fresh()->postponed);

        $playing = $t->matches()->where('status', 'playing')->where('court', 1)->first();
        $manager->postpone($playing);
        $this->assertSame('pending', $playing->fresh()->status);
        $this->assertSame(2, $t->matches()->where('status', 'playing')->count());
    }

    public function test_knockout_winner_cannot_change_once_next_match_started(): void
    {
        $manager = app(TournamentManager::class);
        $t = $this->makeTournament(8);
        $manager->start($t);

        while ($t->fresh()->status === Tournament::GROUPS) {
            $manager->recordResult($t->matches()->where('status', 'playing')->first(), 6, 2);
        }

        // Terminamos las semifinales -> empieza la final
        foreach ($t->matches()->where('stage', 'knockout')->where('bracket_size', 4)->get() as $semi) {
            $manager->recordResult($semi->fresh(), 6, 3);
        }
        $semi = $t->matches()->where('stage', 'knockout')->where('bracket_size', 4)->first();

        // Corregir el marcador sin cambiar el ganador sí se permite
        $manager->recordResult($semi->fresh(), 7, 5);
        $this->assertSame(7, $semi->fresh()->games1);

        $this->expectException(ValidationException::class);
        $manager->recordResult($semi->fresh(), 3, 6);
    }
}

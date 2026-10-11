<?php

namespace Tests\Feature;

use App\Livewire\MatchBoard;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Services\FormatPlanner;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TwoSetMatchesTest extends TestCase
{
    use RefreshDatabase;

    private function twoSetMatch(): TennisMatch
    {
        $tournament = Tournament::create(['name' => 'Torneo', 'date' => '2026-10-10', 'start_games_groups' => TennisMatch::TWO_SETS]);
        foreach (range(1, 4) as $i) {
            $tournament->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        app(TournamentManager::class)->start($tournament);

        return $tournament->matches()->where('court', 1)->sole();
    }

    public function test_straight_sets_and_super_tie_break_decide_the_winner_and_count_games(): void
    {
        $match = $this->twoSetMatch();
        $manager = app(TournamentManager::class);

        $manager->recordSets($match, [[6, 4], [3, 6], [8, 10]]);

        $match->refresh();
        $this->assertSame($match->pair2_id, $match->winner_id);
        // El súper tie-break cuenta como un juego para quien lo gana.
        $this->assertSame([9, 11], [$match->games1, $match->games2]);
        $this->assertSame('6-4 3-6 8-10', $match->scoreLabel());

        $manager->recordSets($match, [[6, 4], [7, 5]]);

        $this->assertSame($match->pair1_id, $match->refresh()->winner_id);
        $this->assertSame([13, 9], [$match->games1, $match->games2]);
    }

    /** @return array<string, array{list<array{int, int}>}> */
    public static function invalidScores(): array
    {
        return [
            'falta un set' => [[[6, 4]]],
            'set imposible' => [[[6, 5], [6, 4]]],
            'tie-break sobrando' => [[[6, 4], [6, 3], [10, 8]]],
            'falta el tie-break' => [[[6, 4], [3, 6]]],
            'tie-break sin 2 de diferencia' => [[[6, 4], [3, 6], [10, 9]]],
            'tie-break pasado de 10 con más de 2' => [[[6, 4], [3, 6], [13, 9]]],
            'tie-break sin llegar a 10' => [[[6, 4], [3, 6], [9, 7]]],
        ];
    }

    #[DataProvider('invalidScores')]
    public function test_invalid_two_set_scores_are_rejected(array $sets): void
    {
        $match = $this->twoSetMatch();

        try {
            app(TournamentManager::class)->recordSets($match, $sets);
            $this->fail('El marcador debería rechazarse.');
        } catch (ValidationException) {
            $this->assertSame(TennisMatch::PLAYING, $match->refresh()->status);
        }
    }

    public function test_walkover_counts_six_one_in_each_set(): void
    {
        $match = $this->twoSetMatch();

        app(TournamentManager::class)->walkover($match, $match->pair2_id);

        $match->refresh();
        $this->assertSame([[6, 1], [6, 1]], $match->sets);
        $this->assertSame([12, 2], [$match->games1, $match->games2]);
        $this->assertSame($match->pair1_id, $match->winner_id);
    }

    public function test_organizer_enters_each_set_on_the_match_board(): void
    {
        $match = $this->twoSetMatch();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(MatchBoard::class, ['tournament' => $match->tournament])
            ->assertSee('Súper TB')
            ->set("scores.{$match->id}.sets.0", [6, 2])
            ->set("scores.{$match->id}.sets.1", [6, 3])
            ->call('save', $match->id)
            ->assertHasNoErrors();

        $this->assertSame([[6, 2], [6, 3]], $match->refresh()->sets);
    }

    public function test_planner_keeps_proposing_one_set_and_says_where_two_sets_fit(): void
    {
        $planner = app(FormatPlanner::class);

        $roomy = $planner->plan(4, 8 * 60);
        $this->assertSame(0, $roomy['start_games']);
        $this->assertSame('all', $roomy['two_sets']);

        $this->assertSame('knockout', $planner->plan(4, 4 * 60)['two_sets']);
        $this->assertNull($planner->plan(12, 8 * 60)['two_sets']);
    }
}

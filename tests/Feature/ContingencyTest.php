<?php

namespace Tests\Feature;

use App\Livewire\MatchBoard;
use App\Livewire\Pairs;
use App\Models\Pair;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContingencyTest extends TestCase
{
    use RefreshDatabase;

    private TournamentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TournamentManager::class);
        $this->actingAs(User::factory()->admin()->create());
    }

    private function startTournament(int $pairs, bool $consolationAll = false): Tournament
    {
        $tournament = Tournament::create(['name' => 'Test', 'date' => '2026-10-10', 'consolation_all' => $consolationAll]);
        foreach (range(1, $pairs) as $i) {
            $tournament->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        $this->manager->start($tournament);

        return $tournament->refresh();
    }

    /** Termina el primer partido en pista con victoria de la pareja 1. */
    private function playNext(Tournament $tournament): ?TennisMatch
    {
        $match = $tournament->matches()->where('status', TennisMatch::PLAYING)->orderBy('court')->first();
        if ($match) {
            $this->manager->recordResult($match, 6, max(3, $match->startGames()));
        }

        return $match;
    }

    private function playGroupStage(Tournament $tournament): void
    {
        while ($tournament->refresh()->status === Tournament::GROUPS) {
            $this->playNext($tournament);
        }
    }

    public function test_withdrawn_pair_loses_its_playing_and_pending_group_matches_by_walkover(): void
    {
        $tournament = $this->startTournament(8);
        $playing = $tournament->matches()->where('status', TennisMatch::PLAYING)->first();
        $pair = $playing->pair1;

        $this->manager->withdraw($pair);

        $pairMatches = $tournament->matches()->where(fn ($query) => $query->where('pair1_id', $pair->id)->orWhere('pair2_id', $pair->id))->get();
        $this->assertCount(3, $pairMatches);
        foreach ($pairMatches as $match) {
            $this->assertTrue($match->walkover);
            $this->assertSame(TennisMatch::FINISHED, $match->status);
            $this->assertNotSame($pair->id, $match->winner_id);
            $this->assertSame(TournamentManager::walkoverScore($match->start_games), [max($match->games1, $match->games2), min($match->games1, $match->games2)]);
        }

        // La pista que ha quedado libre ya tiene otro partido.
        $this->assertSame(2, $tournament->matches()->where('status', TennisMatch::PLAYING)->count());

        $standings = $this->manager->standings($pair->group);
        $this->assertSame($pair->id, $standings->last()['pair']->id);
        $this->assertTrue($standings->last()['withdrawn']);

        $this->playGroupStage($tournament);
        $knockoutPairs = $tournament->matches()->whereIn('stage', ['knockout', 'consolation'])->get()
            ->flatMap(fn (TennisMatch $match) => [$match->pair1_id, $match->pair2_id]);
        $this->assertNotContains($pair->id, $knockoutPairs);

        foreach (['parejas', 'grupos', 'cuadro', 'partidos'] as $page) {
            $this->get("/torneos/{$tournament->id}/{$page}")->assertOk();
        }
        $this->get("/torneos/{$tournament->id}/grupos")->assertSee('Retirada')->assertSee('W.O.');
    }

    public function test_walkover_gives_six_against_the_starting_score_plus_one(): void
    {
        $expected = [0 => [6, 1], 1 => [6, 2], 2 => [6, 3], 3 => [6, 4], 4 => [7, 5]];

        foreach ($expected as $startGames => $score) {
            $this->assertSame($score, TournamentManager::walkoverScore($startGames));
            $this->assertNull($this->manager->validateScore($score[0], $score[1], $startGames), "W.O. desde {$startGames}-{$startGames}");
        }
    }

    public function test_opponent_advances_when_a_withdrawn_pair_is_waiting_in_the_next_round(): void
    {
        $tournament = $this->startTournament(8);
        $this->playGroupStage($tournament);
        $this->assertSame(4, $tournament->qualifiers);

        $semifinal = $this->playNext($tournament);
        $this->manager->withdraw(Pair::find($semifinal->pair1_id));

        $final = $tournament->matches()->where('stage', 'knockout')->where('bracket_size', 2)->sole();
        $this->assertSame(TennisMatch::PENDING, $final->status);

        $otherSemifinal = $this->playNext($tournament);
        $final->refresh();

        $this->assertTrue($final->walkover);
        $this->assertSame($otherSemifinal->winner_id, $final->winner_id);
    }

    public function test_withdrawal_can_be_undone_during_the_group_stage(): void
    {
        $tournament = $this->startTournament(8);
        $pair = $tournament->matches()->where('status', TennisMatch::PLAYING)->first()->pair1;
        $this->manager->withdraw($pair);

        $this->manager->reinstate($pair->refresh());

        $this->assertNull($pair->refresh()->withdrawn_at);
        $this->assertSame(0, $tournament->matches()->where('walkover', true)->count());
        $this->assertSame(3, $tournament->matches()->where('status', '!=', TennisMatch::FINISHED)
            ->where(fn ($query) => $query->where('pair1_id', $pair->id)->orWhere('pair2_id', $pair->id))->count());
    }

    public function test_withdrawal_cannot_be_undone_once_the_knockout_is_drawn(): void
    {
        $tournament = $this->startTournament(8);
        $pair = $tournament->pairs()->first();
        $this->manager->withdraw($pair);
        $this->playGroupStage($tournament);

        $this->expectException(ValidationException::class);
        $this->manager->reinstate($pair->refresh());
    }

    public function test_late_pair_joins_the_smallest_group_with_its_matches_spread_in_the_queue(): void
    {
        $tournament = $this->startTournament(8);
        $withdrawn = $tournament->pairs()->first();
        $this->manager->withdraw($withdrawn);

        $late = $this->manager->addLatePair($tournament, 'Late', 'Comer');

        $this->assertSame($withdrawn->group_id, $late->group_id);
        $lateMatches = $tournament->matches()->where(fn ($query) => $query->where('pair1_id', $late->id)->orWhere('pair2_id', $late->id))->get();
        $this->assertCount(4, $lateMatches);

        // Contra la retirada gana por W.O.; los demás, repartidos por la cola sin ir seguidos.
        $againstWithdrawn = $lateMatches->first(fn (TennisMatch $match) => in_array($withdrawn->id, [$match->pair1_id, $match->pair2_id]));
        $this->assertTrue($againstWithdrawn->walkover);
        $this->assertSame($late->id, $againstWithdrawn->winner_id);

        $queuePositions = $tournament->matches()->where('status', '!=', TennisMatch::FINISHED)->orderBy('queue_order')->pluck('id');
        $latePositions = $lateMatches->reject(fn ($match) => $match->is($againstWithdrawn))
            ->map(fn (TennisMatch $match) => $queuePositions->search($match->id))->sort()->values();
        for ($i = 1; $i < $latePositions->count(); $i++) {
            $this->assertGreaterThan(1, $latePositions[$i] - $latePositions[$i - 1]);
        }
    }

    public function test_numbers_and_draw_are_assigned_only_once_every_pair_is_registered(): void
    {
        $tournament = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);
        foreach (range(1, 8) as $i) {
            $tournament->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }

        // En inscripción nadie tiene número: no depende del orden en que se apuntan.
        $this->assertSame(0, $tournament->pairs()->whereNotNull('number')->count());

        // Al empezar: números seguidos por grupo (A del 1 al 4, B del 5 al 8) y un puesto de sorteo para cada pareja.
        $this->manager->start($tournament);
        foreach ($tournament->groups()->get() as $index => $group) {
            $this->assertSame(range($index * 4 + 1, $index * 4 + 4), $group->pairs()->orderBy('number')->pluck('number')->all());
        }
        $this->assertSame(range(1, 8), $tournament->pairs()->orderBy('draw_position')->pluck('draw_position')->all());

        $late = $this->manager->addLatePair($tournament->refresh(), 'Late', 'Comer');

        $this->assertSame(9, $late->number);
        $this->assertSame(9, $late->draw_position);
        $this->get(route('tournaments.groups', $tournament))->assertSee('title="Pareja 9"', false);
    }

    public function test_late_pairs_are_only_accepted_during_the_group_stage(): void
    {
        $tournament = $this->startTournament(4);
        $this->playGroupStage($tournament);

        $this->expectException(ValidationException::class);
        $this->manager->addLatePair($tournament, 'Late', 'Comer');
    }

    public function test_closed_court_sends_its_match_back_to_the_front_of_the_queue(): void
    {
        $tournament = $this->startTournament(8);
        $onCourtTwo = $tournament->matches()->where('court', 2)->sole();

        $this->manager->closeCourt($tournament, 2);

        $this->assertSame(TennisMatch::PENDING, $onCourtTwo->refresh()->status);
        $this->assertSame($onCourtTwo->id, $this->manager->upcoming($tournament)->first()->id);
        $this->assertFalse($tournament->matches()->where('court', 2)->where('status', TennisMatch::PLAYING)->exists());

        // Al quedar libre la pista 1, entra el partido que se había interrumpido.
        $this->playNext($tournament);
        $this->assertSame(1, $onCourtTwo->refresh()->court);
        $this->assertFalse($tournament->matches()->where('court', 2)->where('status', TennisMatch::PLAYING)->exists());

        $this->manager->closeCourt($tournament->refresh(), 1);
        $this->assertTrue($tournament->refresh()->finishEstimate()['paused']);
        $this->get(route('tournaments.matches', $tournament))->assertSee('Reabrir pista')->assertSee('Todas las pistas cerradas');

        $this->manager->openCourt($tournament, 2);
        $this->assertSame($onCourtTwo->id, $tournament->matches()->where('court', 2)->where('status', TennisMatch::PLAYING)->sole()->id);
    }

    public function test_fewer_courts_mid_tournament_returns_the_extra_court_match_to_the_queue(): void
    {
        $tournament = $this->startTournament(8);
        $onCourtTwo = $tournament->matches()->where('court', 2)->sole();

        Livewire::test(Pairs::class, ['tournament' => $tournament])
            ->set('courts', 1)
            ->set('consolationAll', true)
            ->set('start_time', '08:00');

        $tournament->refresh();
        $this->assertSame(1, $tournament->courts);
        $this->assertFalse($tournament->consolation_all);
        $this->assertSame('09:00', substr($tournament->start_time, 0, 5));
        $this->assertSame(TennisMatch::PENDING, $onCourtTwo->refresh()->status);
        $this->assertSame(1, $tournament->matches()->where('status', TennisMatch::PLAYING)->count());
    }

    public function test_board_rejects_a_result_already_saved_from_another_device(): void
    {
        $tournament = $this->startTournament(8);
        $match = $tournament->matches()->where('court', 1)->sole();

        $board = Livewire::test(MatchBoard::class, ['tournament' => $tournament])
            ->set("scores.{$match->id}.g1", 6)
            ->set("scores.{$match->id}.g2", 1);

        $this->manager->recordResult($match, 2, 6);

        $board->call('save', $match->id)->assertHasErrors("score.{$match->id}");
        $this->assertSame([2, 6], [$match->refresh()->games1, $match->games2]);
    }

    public function test_walkover_of_a_single_match_keeps_the_pair_in_the_tournament(): void
    {
        $tournament = $this->startTournament(8);
        $match = $tournament->matches()->where('court', 1)->sole();

        Livewire::test(MatchBoard::class, ['tournament' => $tournament])
            ->call('walkover', $match->id, $match->pair2_id);

        $match->refresh();
        $this->assertTrue($match->walkover);
        $this->assertSame($match->pair1_id, $match->winner_id);
        $this->assertNull($match->pair2->withdrawn_at);
        $this->assertTrue($tournament->matches()->where('status', TennisMatch::PENDING)->where(fn ($query) => $query->where('pair1_id', $match->pair2_id)->orWhere('pair2_id', $match->pair2_id))->exists());
    }

    public function test_substitute_keeps_the_pair_results(): void
    {
        $tournament = $this->startTournament(8);
        $match = $this->playNext($tournament);

        Livewire::test(Pairs::class, ['tournament' => $tournament])
            ->call('startEditing', $match->pair1_id)
            ->set('editPlayer2', 'Sustituta')
            ->call('saveEditing')
            ->assertHasNoErrors()
            ->assertSee($match->pair1->player1.' / Sustituta');

        $this->assertSame($match->pair1_id, $match->refresh()->winner_id);
    }

    public function test_pairs_cannot_withdraw_before_the_tournament_starts(): void
    {
        $tournament = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);
        $pair = $tournament->pairs()->create(['player1' => 'A', 'player2' => 'B']);

        $this->expectException(ValidationException::class);
        $this->manager->withdraw($pair);
    }

    public static function contingencyScenarios(): array
    {
        return [
            '5 parejas' => [5, false],
            '8 parejas' => [8, false],
            '8 parejas, consolación para todos' => [8, true],
            '11 parejas' => [11, false],
            '13 parejas, consolación para todos' => [13, true],
            '16 parejas' => [16, false],
        ];
    }

    /** Retiradas, pareja tardía y pistas cerradas a lo largo del día: el torneo siempre termina. */
    #[DataProvider('contingencyScenarios')]
    public function test_tournament_always_reaches_the_end_despite_contingencies(int $pairs, bool $consolationAll): void
    {
        $tournament = $this->startTournament($pairs, $consolationAll);
        $step = 0;

        while ($tournament->refresh()->status !== Tournament::FINISHED && $step < 200) {
            $step++;

            match ($step) {
                2 => $this->manager->withdraw($tournament->pairs()->whereNull('withdrawn_at')->first()),
                3 => $tournament->status === Tournament::GROUPS && $tournament->pairs()->count() < config('torneo.max_pairs')
                    ? $this->manager->addLatePair($tournament, 'Late', 'Comer') : null,
                4 => $this->manager->closeCourt($tournament, 1),
                6 => $this->manager->openCourt($tournament, 1),
                default => null,
            };

            // Ya en la fase final, se retira alguien que sigue vivo en el cuadro.
            if ($tournament->refresh()->status === Tournament::KNOCKOUT && ! isset($knockoutWithdrawal)) {
                $knockoutWithdrawal = $tournament->matches()->where('stage', 'knockout')->where('status', '!=', TennisMatch::FINISHED)
                    ->whereNotNull('pair1_id')->value('pair1_id');
                $this->manager->withdraw(Pair::find($knockoutWithdrawal));
            }

            if ($tournament->refresh()->status !== Tournament::FINISHED) {
                $this->assertNoCourtIsIdle($tournament, $step);
                $this->assertNotNull($this->playNext($tournament), "Atasco en el paso {$step} ({$tournament->status})");
            }
        }

        $this->assertSame(Tournament::FINISHED, $tournament->status);
        $this->assertFalse($tournament->matches()->where('status', '!=', TennisMatch::FINISHED)->exists());
        $this->assertTrue($tournament->finishEstimate()['finished']);

        // Ninguna pareja retirada gana un partido después de retirarse.
        foreach ($tournament->pairs()->whereNotNull('withdrawn_at')->get() as $withdrawn) {
            $this->assertFalse($tournament->matches()->where('winner_id', $withdrawn->id)
                ->where('finished_at', '>', $withdrawn->withdrawn_at)->exists());
        }
    }

    /** Ninguna pista abierta se queda libre mientras haya un partido que pueda jugarse en ella. */
    private function assertNoCourtIsIdle(Tournament $tournament, int $step): void
    {
        $manager = app(TournamentManager::class);
        $freeCourts = array_diff($tournament->openCourts(), $tournament->matches()->where('status', TennisMatch::PLAYING)->pluck('court')->all());
        $busy = $manager->busyPairIds($tournament);
        $canStart = $manager->upcoming($tournament)->contains(fn (TennisMatch $match) => $manager->isReady($match, $busy));

        $this->assertFalse($freeCourts && $canStart, "Pista libre con partidos listos en el paso {$step}");
    }
}

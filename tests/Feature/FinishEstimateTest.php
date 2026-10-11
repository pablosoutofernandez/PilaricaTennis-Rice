<?php

namespace Tests\Feature;

use App\Livewire\TournamentIndex;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinishEstimateTest extends TestCase
{
    use RefreshDatabase;

    /** 4 parejas en 2 pistas: grupo de 4 (3 tandas) + final, sets desde 0-0 (40′ + 5′ de cambio). */
    private function startFourPairTournament(): Tournament
    {
        $tournament = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);
        foreach (range(1, 4) as $i) {
            $tournament->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        app(TournamentManager::class)->start($tournament);

        return $tournament->refresh();
    }

    public function test_estimate_includes_the_final_that_is_not_drawn_yet_and_uses_the_organization_margin(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $tournament = $this->startFourPairTournament();

        $estimate = $tournament->finishEstimate();

        // 4 tandas × 45′ × 1,10 de margen, sin el último cambio de pista.
        $this->assertSame('12:12', $estimate['finish']->format('H:i'));
        $this->assertSame(-468, $estimate['delay_minutes']);
        $this->assertFalse($estimate['finished']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('tournaments.matches', $tournament))
            ->assertOk()
            ->assertSee('Fin estimado')
            ->assertSee('12:12')
            ->assertSee('media prevista')
            ->assertSee('50 min');
    }

    public function test_estimate_follows_the_real_pace_of_finished_matches(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $tournament = $this->startFourPairTournament();

        $this->travelTo('2026-10-10 10:00');
        foreach ($tournament->matches()->where('status', TennisMatch::PLAYING)->get() as $match) {
            app(TournamentManager::class)->recordResult($match, 6, 2);
        }

        $estimate = $tournament->refresh()->finishEstimate();

        // Los partidos duran 60′ en lugar de 45′: quedan 3 tandas a ese ritmo.
        $this->assertSame(1.33, $estimate['pace']);
        $this->assertSame('12:52', $estimate['finish']->format('H:i'));
        $this->assertSame(60, $estimate['average_minutes']);
        $this->assertTrue($estimate['average_is_real']);
    }

    public function test_finished_tournament_shows_the_real_finish_time(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $tournament = $this->startFourPairTournament();

        while ($match = $tournament->matches()->where('status', TennisMatch::PLAYING)->first()) {
            $this->travel(30)->minutes();
            app(TournamentManager::class)->recordResult($match, 6, 3);
        }

        $estimate = $tournament->refresh()->finishEstimate();

        $this->assertSame(Tournament::FINISHED, $tournament->status);
        $this->assertTrue($estimate['finished']);
        $this->assertSame('12:30', $estimate['finish']->format('H:i'));
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('tournaments.matches', $tournament))
            ->assertSee('Terminó a las');
    }

    public function test_admin_restarts_the_clock_and_the_first_matches_are_called_by_hand(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $tournament = $this->startFourPairTournament();
        $this->travelTo('2026-10-10 10:00');
        $finished = $tournament->matches()->where('status', TennisMatch::PLAYING)->first();
        app(TournamentManager::class)->recordResult($finished, 6, 2);

        $this->travelTo('2026-10-10 10:30');
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(TournamentIndex::class)
            ->call('restartClock', $tournament->id)
            ->assertSee('Tiempos reiniciados');

        $this->assertFalse($tournament->matches()->where('status', TennisMatch::PLAYING)->exists());
        $first = app(TournamentManager::class)->upcoming($tournament)->first();
        $this->assertNull($first->started_at);

        // Hasta que alguien los llama, las pistas salen vacías.
        $this->get('/')->assertSeeInOrder(['PISTA 1', 'Sin partido ahora mismo', 'PISTA 2', 'Sin partido ahora mismo']);

        // Quien organiza llama a mano a los primeros partidos y el reloj cuenta desde entonces.
        $this->travelTo('2026-10-10 10:40');
        app(TournamentManager::class)->sendToCourt($first, 1);
        $this->assertSame('10:40', $first->refresh()->started_at->format('H:i'));
        $this->assertFalse($tournament->matches()->where('court', 2)->where('status', TennisMatch::PLAYING)->exists());
        // El resultado se queda, pero ya no cuenta para la media.
        $this->assertSame([6, 2], [$finished->refresh()->games1, $finished->games2]);
        $this->assertFalse($tournament->refresh()->finishEstimate()['average_is_real']);
    }

    public function test_no_estimate_during_registration(): void
    {
        $tournament = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);

        $this->assertNull($tournament->finishEstimate());
    }
}

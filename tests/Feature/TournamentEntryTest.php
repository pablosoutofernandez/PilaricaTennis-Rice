<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentEntryTest extends TestCase
{
    use RefreshDatabase;

    private function tournamentWithPairs(): Tournament
    {
        $tournament = Tournament::create(['name' => 'Torneo Pilarica', 'date' => '2026-10-10']);
        foreach (range(1, 8) as $i) {
            $tournament->pairs()->create(['player1' => "Jugador {$i}", 'player2' => "Compañero {$i}"]);
        }

        return $tournament;
    }

    public function test_guests_get_the_entry_screen_on_the_tournament_pages(): void
    {
        $tournament = $this->tournamentWithPairs();

        foreach ([route('home'), route('tournaments.groups', $tournament)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Entrar al torneo')
                ->assertSee('<option value="Jugador 3">Jugador 3</option>', false);
        }
    }

    public function test_entry_screen_is_not_shown_to_users_on_the_login_page_or_once_finished(): void
    {
        $tournament = $this->tournamentWithPairs();

        $this->get(route('login'))->assertOk()->assertDontSee('Entrar al torneo');
        $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertDontSee('Entrar al torneo');

        auth()->logout();
        $tournament->update(['status' => Tournament::FINISHED]);
        $this->get(route('home'))->assertOk()->assertDontSee('Entrar al torneo');
    }

    public function test_each_player_gets_their_partner_pair_number_and_group_without_withdrawn_pairs(): void
    {
        $tournament = $this->tournamentWithPairs();
        app(TournamentManager::class)->start($tournament);
        $pair = $tournament->pairs()->where('player1', 'Jugador 1')->sole();
        $tournament->pairs()->where('player1', 'Jugador 2')->update(['withdrawn_at' => now()]);
        $tournament->pairs()->where('player1', 'Jugador 8')->update(['player1' => 'Álvaro']);

        $players = collect($tournament->playersWithPartners());

        $this->assertCount(14, $players);
        $this->assertNull($players->firstWhere('name', 'Jugador 2'));
        // Orden alfabético sin que la tilde mande al final.
        $this->assertSame(['Álvaro', 'Compañero 1', 'Compañero 3'], $players->pluck('name')->take(3)->all());
        $this->assertSame(
            ['name' => 'Compañero 1', 'partner' => 'Jugador 1', 'number' => $pair->number, 'group' => $pair->group->name],
            $players->firstWhere('name', 'Compañero 1'),
        );
    }
}

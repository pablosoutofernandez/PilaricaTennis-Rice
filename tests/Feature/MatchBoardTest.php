<?php

namespace Tests\Feature;

use App\Livewire\Bracket;
use App\Livewire\Formats;
use App\Livewire\Groups;
use App\Livewire\MatchBoard;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MatchBoardTest extends TestCase
{
    use RefreshDatabase;

    /** Las pruebas de gestión las hace el administrador; los permisos se prueban en AccessTest. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_result_can_be_entered_and_corrected_from_the_board(): void
    {
        $t = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);
        foreach (range(1, 8) as $i) {
            $t->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        app(TournamentManager::class)->start($t);
        $match = $t->matches()->where('court', 1)->where('status', 'playing')->first();

        Livewire::test(MatchBoard::class, ['tournament' => $t])
            ->set("scores.{$match->id}.g1", 6)
            ->set("scores.{$match->id}.g2", 5)
            ->call('save', $match->id)
            ->assertHasErrors("score.{$match->id}")
            ->set("scores.{$match->id}.g2", 3)
            ->call('save', $match->id)
            ->assertHasNoErrors()
            ->call('edit', $match->id)
            ->set("scores.{$match->id}.g1", 2)
            ->set("scores.{$match->id}.g2", 6)
            ->call('save', $match->id)
            ->assertHasNoErrors();

        $match->refresh();
        $this->assertSame('finished', $match->status);
        $this->assertSame($match->pair2_id, $match->winner_id);
        // La pista 1 ya tiene el siguiente partido
        $this->assertTrue($t->matches()->where('court', 1)->where('status', 'playing')->exists());
    }

    public function test_pages_render(): void
    {
        $t = Tournament::create(['name' => 'Test', 'date' => '2026-10-10']);
        foreach (range(1, 6) as $i) {
            $t->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        foreach (['/', '/admin', '/admin/formatos', '/admin/usuarios', "/torneos/{$t->id}/parejas", "/torneos/{$t->id}/partidos"] as $url) {
            $this->get($url)->assertOk();
        }

        app(TournamentManager::class)->start($t);
        foreach (['grupos', 'cuadro', 'partidos', 'parejas'] as $page) {
            $this->get("/torneos/{$t->id}/{$page}")->assertOk();
        }
    }

    public function test_format_proposals_toggle_consolation_for_group_eliminations(): void
    {
        Livewire::test(Formats::class)
            ->assertSee('Incluir a las parejas eliminadas en grupos')
            ->assertSee('4 parejas · 3 partidos')
            ->set('consolationAll', true)
            ->assertSee('8 parejas · 7 partidos')
            ->assertSee('★ Recomendado')
            ->assertSee('Las 5 parejas eliminadas en grupos juegan un cuadro aparte desde cuartos de final (las 3 mejor clasificadas pasan directas a la siguiente ronda).')
            ->set('consolationAll', false)
            ->assertSee('Las 4 parejas que pierden en cuartos de final juegan un cuadro aparte desde semifinales. Las eliminadas en grupos no siguen jugando.')
            ->assertSee('Sin consolación.');
    }

    private function startTournament(int $pairs, bool $consolationAll = false): Tournament
    {
        $t = Tournament::create(['name' => 'Test', 'date' => '2026-10-10', 'consolation_all' => $consolationAll]);
        foreach (range(1, $pairs) as $i) {
            $t->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        app(TournamentManager::class)->start($t);

        return $t->refresh();
    }

    public function test_each_court_keeps_its_next_match_and_gets_a_new_one_when_it_starts(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $t = $this->startTournament(8);

        $board = Livewire::test(MatchBoard::class, ['tournament' => $t]);
        $nextOnCourt = $board->viewData('nextOnCourt');
        [$nextOne, $nextTwo] = [$nextOnCourt[1], $nextOnCourt[2]];
        $firstUnassigned = $board->viewData('afterNext')->first();

        // Ninguna pareja avisada en una pista está jugando en la otra.
        $playing = $t->matches()->where('status', TennisMatch::PLAYING)->get()->keyBy('court');
        $this->assertEmpty(array_intersect([$nextOne->pair1_id, $nextOne->pair2_id], [$playing[2]->pair1_id, $playing[2]->pair2_id]));
        $this->assertEmpty(array_intersect([$nextTwo->pair1_id, $nextTwo->pair2_id], [$playing[1]->pair1_id, $playing[1]->pair2_id]));
        // 45′ de partido + cambio con el 10 % de margen.
        $this->assertSame('09:49', $board->viewData('eta')[$nextOne->id]->format('H:i'));
        $board->assertSee('Siguiente')->assertDontSee('Resultados');

        // Acaba antes la pista 2: entra su siguiente y el primero sin pista pasa a ser el nuevo siguiente de la 2.
        $this->travelTo('2026-10-10 09:20');
        app(TournamentManager::class)->recordResult($playing[2], 6, 2);

        $this->assertSame(2, $nextTwo->refresh()->court);
        $this->assertSame(TennisMatch::PLAYING, $nextTwo->status);
        $this->assertSame(1, $nextOne->refresh()->next_on_court);
        $this->assertSame(2, $firstUnassigned->refresh()->next_on_court);
    }

    public function test_results_are_corrected_from_groups_and_bracket(): void
    {
        $t = $this->startTournament(4);
        $match = $t->matches()->where('court', 1)->sole();
        app(TournamentManager::class)->recordResult($match, 6, 2);

        Livewire::test(Groups::class, ['tournament' => $t])
            ->call('edit', $match->id)
            ->assertSee('Corrigiendo')
            ->set("scores.{$match->id}.g1", 3)
            ->set("scores.{$match->id}.g2", 6)
            ->call('save', $match->id)
            ->assertHasNoErrors();
        $this->assertSame($match->pair2_id, $match->refresh()->winner_id);

        while ($t->refresh()->status === Tournament::GROUPS) {
            $playing = $t->matches()->where('status', TennisMatch::PLAYING)->first();
            app(TournamentManager::class)->recordResult($playing, 6, 1);
        }
        $final = $t->matches()->where('stage', 'knockout')->sole();
        app(TournamentManager::class)->recordResult($final, 6, 4);

        Livewire::test(Bracket::class, ['tournament' => $t])
            ->call('edit', $final->id)
            ->set("scores.{$final->id}.g1", 7)
            ->set("scores.{$final->id}.g2", 5)
            ->call('save', $final->id)
            ->assertHasNoErrors()
            ->assertSee('Campeones');
        $this->assertSame(7, $final->refresh()->games1);
    }

    public function test_bracket_keeps_an_empty_slot_for_each_bye(): void
    {
        $t = $this->startTournament(9, consolationAll: true);
        while ($t->refresh()->status === Tournament::GROUPS) {
            app(TournamentManager::class)->recordResult($t->matches()->where('status', TennisMatch::PLAYING)->first(), 6, 1);
        }

        $rounds = Livewire::test(Bracket::class, ['tournament' => $t])->viewData('consolationRounds');

        // 5 parejas en un cuadro de 8: 1 partido de cuartos y 3 byes.
        $quarterFinals = $rounds[8]['slots'];
        $this->assertCount(4, $quarterFinals);
        $this->assertCount(3, array_filter($quarterFinals, fn ($slot) => $slot === null));
        foreach ($rounds as $size => $round) {
            foreach ($round['slots'] as $index => $slot) {
                $this->assertTrue($slot === null || $slot->position === $index + 1);
            }
        }
    }
}

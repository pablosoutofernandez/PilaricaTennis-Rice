<?php

namespace Tests\Feature;

use App\Livewire\Formats;
use App\Livewire\MatchBoard;
use App\Models\Tournament;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MatchBoardTest extends TestCase
{
    use RefreshDatabase;

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
        foreach (['/', '/formatos', "/torneos/{$t->id}/parejas", "/torneos/{$t->id}/partidos"] as $url) {
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
}

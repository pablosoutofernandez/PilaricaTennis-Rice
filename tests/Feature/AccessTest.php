<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Bracket;
use App\Livewire\Groups;
use App\Livewire\MatchBoard;
use App\Livewire\Pairs;
use App\Livewire\TournamentIndex;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private function startedTournament(string $name = 'Torneo Pilarica'): Tournament
    {
        $tournament = Tournament::create(['name' => $name, 'date' => '2026-10-10']);
        foreach (range(1, 8) as $i) {
            $tournament->pairs()->create(['player1' => "A{$i}", 'player2' => "B{$i}"]);
        }
        app(TournamentManager::class)->start($tournament);

        return $tournament->refresh();
    }

    public function test_guests_see_the_dashboard_and_tournament_pages_without_management_controls_or_estimates(): void
    {
        $tournament = $this->startedTournament();
        $tournament->organizers()->attach(User::factory()->create(['name' => 'Lucía']));

        $this->get('/')
            ->assertOk()
            ->assertSee('Torneo Pilarica')
            ->assertSee('Organiza: <span class="font-semibold text-white">Lucía</span>', false)
            ->assertSee('Acceso organización')
            ->assertDontSee('Admin')
            ->assertDontSee('Fin estimado')
            ->assertDontSee('por partido');

        foreach (['parejas', 'grupos', 'cuadro', 'partidos'] as $page) {
            $this->get("/torneos/{$tournament->id}/{$page}")
                ->assertOk()
                ->assertDontSee('Fin estimado')
                ->assertDontSee('por partido');
        }
        // En Partidos sí ven la hora aproximada de los siguientes y a quién pedir que publique un resultado.
        $this->get(route('tournaments.matches', $tournament))
            ->assertSee('≈')
            ->assertSee('Para publicar un resultado, ponte en contacto con <strong>Lucía</strong>', false)
            ->assertDontSee('Guardar resultado')
            ->assertDontSee('Cerrar pista')
            ->assertDontSee('¿a cuánto se empieza?');
        $this->get(route('tournaments.pairs', $tournament))
            ->assertDontSee('Retirar')
            ->assertDontSee('Añadir al torneo');
    }

    /** @return array<string, array{class-string, string, array<int, mixed>}> */
    public static function managementActions(): array
    {
        return [
            'añadir pareja' => [Pairs::class, 'add', []],
            'retirar pareja' => [Pairs::class, 'withdraw', ['pair']],
            'editar pareja' => [Pairs::class, 'startEditing', ['pair']],
            'ajustes' => [Pairs::class, 'saveSettings', []],
            'guardar resultado' => [MatchBoard::class, 'save', ['match']],
            'aplazar' => [MatchBoard::class, 'postpone', ['match']],
            'W.O.' => [MatchBoard::class, 'walkover', ['match', 'pair']],
            'cerrar pista' => [MatchBoard::class, 'closeCourt', [1]],
            'marcador fase final' => [MatchBoard::class, 'setStartGames', ['knockout', 2]],
            'corregir en grupos' => [Groups::class, 'edit', ['match']],
            'corregir en cuadro' => [Bracket::class, 'edit', ['match']],
        ];
    }

    #[DataProvider('managementActions')]
    public function test_guests_and_organizers_of_other_tournaments_cannot_change_anything(string $component, string $method, array $arguments): void
    {
        $tournament = $this->startedTournament();
        $match = $tournament->matches()->where('court', 1)->sole();
        $arguments = array_map(fn ($argument) => match ($argument) {
            'match' => $match->id,
            'pair' => $match->pair1_id,
            default => $argument,
        }, $arguments);

        Livewire::test($component, ['tournament' => $tournament])->call($method, ...$arguments)->assertForbidden();

        $otherOrganizer = User::factory()->create();
        $otherOrganizer->tournaments()->attach(Tournament::create(['name' => 'Otro', 'date' => '2026-11-11']));
        Livewire::actingAs($otherOrganizer)->test($component, ['tournament' => $tournament])->call($method, ...$arguments)->assertForbidden();

        $this->assertSame(TennisMatch::PLAYING, $match->refresh()->status);
        $this->assertSame(0, $tournament->pairs()->whereNotNull('withdrawn_at')->count());
    }

    public function test_organizer_manages_the_tournament_assigned_by_the_admin(): void
    {
        $tournament = $this->startedTournament();
        $organizer = User::factory()->create();
        $match = $tournament->matches()->where('court', 1)->sole();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(Users::class)
            ->assertSee('pendiente de validar')
            ->call('toggleOrganizer', $organizer->id, $tournament->id);

        Livewire::actingAs($organizer)
            ->test(MatchBoard::class, ['tournament' => $tournament])
            ->set("scores.{$match->id}.g1", 6)
            ->set("scores.{$match->id}.g2", 2)
            ->call('save', $match->id)
            ->assertHasNoErrors();

        $this->assertSame(TennisMatch::FINISHED, $match->refresh()->status);
    }

    public function test_admin_area_is_only_for_the_admin(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/formatos')->assertForbidden();
        Livewire::test(TournamentIndex::class)->assertForbidden();
        Livewire::test(Users::class)->assertForbidden();

        $this->actingAs(User::factory()->admin()->create());
        foreach (['/admin', '/admin/formatos', '/admin/usuarios'] as $url) {
            $this->get($url)->assertOk()->assertSee('Administración');
        }
    }

    public function test_seeded_admin_logs_in_with_user_name_and_wrong_password_is_rejected(): void
    {
        $this->seed();

        Livewire::test(Login::class)
            ->set('name', 'Pablo')
            ->set('password', 'otra')
            ->call('login')
            ->assertHasErrors('name');
        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('name', 'Pablo')
            ->set('password', 'abc123.')
            ->call('login')
            ->assertRedirect(route('home'));
        $this->assertTrue(auth()->user()->is_admin);
    }

    public function test_seeding_again_keeps_the_admin_password(): void
    {
        $this->seed();
        User::where('name', 'Pablo')->sole()->update(['password' => 'otra-clave']);

        $this->seed();

        $this->assertSame(1, User::where('name', 'Pablo')->count());
        $this->assertTrue(auth()->attempt(['name' => 'Pablo', 'password' => 'otra-clave']));
    }

    public function test_new_users_register_without_any_permission(): void
    {
        $tournament = $this->startedTournament();

        Livewire::test(Register::class)
            ->set('name', 'Lucía')
            ->set('password', 'secreta1')
            ->set('password_confirmation', 'secreta1')
            ->call('register')
            ->assertRedirect(route('home'));

        $user = User::where('name', 'Lucía')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->can('manage', $tournament));
    }

    public function test_final_phase_start_options_show_the_finish_for_each_score(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $tournament = $this->startedTournament();

        $options = Livewire::actingAs(User::factory()->admin()->create())
            ->test(MatchBoard::class, ['tournament' => $tournament])
            ->assertSee('¿a cuánto se empieza?')
            ->viewData('knockoutStartOptions');

        $this->assertSame([0, 1, 2, 3, 4], array_keys($options));
        // Empezar más avanzado acorta el día.
        $this->assertTrue($options[4]->lessThan($options[0]));
    }
}

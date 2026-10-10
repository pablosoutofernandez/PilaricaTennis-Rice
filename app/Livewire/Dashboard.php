<?php

namespace App\Livewire;

use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\TournamentManager;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Portada pública: el torneo principal en grande y en directo. */
#[Title('Inicio')]
class Dashboard extends Component
{
    public function render()
    {
        $tournaments = Tournament::withCount('pairs')->latest('date')->get();
        $featured = $tournaments->find(Tournament::featured()?->id);

        return view('livewire.dashboard', [
            'featured' => $featured,
            'others' => $tournaments->reject(fn (Tournament $tournament) => $tournament->is($featured))->values(),
            'live' => $featured ? $this->live($featured) : null,
        ]);
    }

    /**
     * @return array{playing: Collection, nextUp: Collection, latest: Collection, played: int, total: int, groups: int, champion: mixed, organizers: Collection, estimate: ?array}
     */
    private function live(Tournament $tournament): array
    {
        $matches = $tournament->matches()->with(['pair1', 'pair2', 'group', 'tournament'])->get();
        $final = $matches->first(fn (TennisMatch $match) => $match->stage === 'knockout' && $match->bracket_size === 2 && ! $match->third_place);

        return [
            'playing' => $matches->where('status', TennisMatch::PLAYING)->keyBy('court'),
            'nextUp' => app(TournamentManager::class)->upcoming($tournament)->take(2),
            'latest' => $matches->where('status', TennisMatch::FINISHED)->sortByDesc('finished_at')->take(5)->values(),
            'played' => $matches->where('status', TennisMatch::FINISHED)->count(),
            'total' => $matches->count(),
            'groups' => $tournament->groups()->count(),
            'champion' => $final?->winner_id ? ($final->winner_id === $final->pair1_id ? $final->pair1 : $final->pair2) : null,
            'organizers' => $tournament->organizerNames(),
            // Las estimaciones de hora solo las ve quien organiza.
            'estimate' => auth()->user()?->can('manage', $tournament) ? $tournament->finishEstimate() : null,
        ];
    }
}

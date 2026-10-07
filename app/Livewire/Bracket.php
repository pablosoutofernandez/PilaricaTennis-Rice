<?php

namespace App\Livewire;

use App\Livewire\Concerns\EditsResults;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\FormatPlanner;
use Illuminate\Support\Collection;
use Livewire\Component;

class Bracket extends Component
{
    use EditsResults;

    public Tournament $tournament;

    /**
     * Huecos de una ronda en orden de cuadro; null donde una pareja pasa directa (bye),
     * para que cada partido quede a la altura de los dos que lo alimentan.
     *
     * @return array<int, ?TennisMatch>
     */
    private function slots(Collection $matches, int $bracketSize): array
    {
        $byPosition = $matches->keyBy('position');

        return array_map(fn (int $position) => $byPosition->get($position), range(1, intdiv($bracketSize, 2)));
    }

    public function render()
    {
        $this->tournament->refresh();

        $matches = $this->tournament->matches()
            ->with(['pair1', 'pair2'])
            ->whereIn('stage', ['knockout', 'consolation'])
            ->orderBy('position')
            ->get();

        $mainMatches = $matches->where('stage', 'knockout');
        $consolationMatches = $matches->where('stage', 'consolation');
        $rounds = $mainMatches->where('third_place', false)
            ->groupBy('bracket_size')
            ->sortKeysDesc()
            ->map(fn ($ms, $size) => [
                'name' => $this->tournament->qualifiers === 6 && $size === 8
                    ? 'Ronda previa'
                    : FormatPlanner::roundName($size),
                'slots' => $this->slots($ms, $size),
            ]);
        $consolationRounds = $consolationMatches->where('third_place', false)
            ->groupBy('bracket_size')
            ->sortKeysDesc()
            ->map(fn ($ms, $size) => ['name' => FormatPlanner::roundName($size), 'slots' => $this->slots($ms, $size)]);

        $final = $mainMatches->first(fn ($m) => $m->bracket_size === 2 && ! $m->third_place);
        $consolationFinal = $consolationMatches->first(fn ($m) => $m->bracket_size === 2);
        $consolationChampion = $consolationFinal?->winner_id
            ? ($consolationFinal->winner_id === $consolationFinal->pair1_id ? $consolationFinal->pair1 : $consolationFinal->pair2)
            : null;

        if (! $consolationChampion && ! $this->tournament->consolation_all && $this->tournament->qualifiers === 2) {
            $firstRound = $mainMatches->first(fn ($match) => $match->round === 1 && $match->isFinished());
            if ($firstRound) {
                $loserId = $firstRound->winner_id === $firstRound->pair1_id ? $firstRound->pair2_id : $firstRound->pair1_id;
                $consolationChampion = $firstRound->pair1_id === $loserId ? $firstRound->pair1 : $firstRound->pair2;
            }
        }

        return view('livewire.bracket', [
            'rounds' => $rounds,
            'consolationRounds' => $consolationRounds,
            'thirdPlace' => $mainMatches->firstWhere('third_place', true),
            'champion' => $final?->winner_id ? ($final->winner_id === $final->pair1_id ? $final->pair1 : $final->pair2) : null,
            'consolationChampion' => $consolationChampion,
        ])->title($this->tournament->name.' · Cuadro');
    }
}

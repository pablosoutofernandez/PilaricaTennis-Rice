<?php

namespace App\Livewire;

use App\Models\Tournament;
use App\Services\TournamentManager;
use Livewire\Component;

class Groups extends Component
{
    public Tournament $tournament;

    public function render(TournamentManager $manager)
    {
        $this->tournament->refresh();
        $groups = $this->tournament->groups()->with(['matches.pair1', 'matches.pair2'])->get();

        // Parejas que pasarían ahora mismo (o que han pasado) a la fase final
        $qualified = [];
        $perGroup = 0;
        if ($groups->isNotEmpty()) {
            $qualified = collect($manager->seededQualifiers($this->tournament))->map(fn ($s) => $s['pair']->id)->all();
            $perGroup = intdiv($this->tournament->qualifiers, $groups->count());
        }

        return view('livewire.groups', [
            'groups' => $groups->map(fn ($g) => [
                'group' => $g,
                'standings' => $manager->standings($g),
                'matches' => $g->matches->sortBy('queue_order'),
            ]),
            'qualified' => $qualified,
            'perGroup' => $perGroup,
        ])->title($this->tournament->name.' · Grupos');
    }
}

<?php

namespace App\Livewire;

use App\Services\FormatPlanner;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Formatos')]
class Formats extends Component
{
    public int $hours = 11;

    public int $courts = 2;

    public bool $consolationAll = false;

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function render(FormatPlanner $planner)
    {
        $minutes = max(60, $this->hours * 60);
        $courts = max(1, $this->courts);

        return view('livewire.formats', [
            'rows' => collect(range(config('torneo.min_pairs'), config('torneo.max_pairs')))
                ->map(fn ($n) => $planner->plan($n, $minutes, $courts, $this->consolationAll)),
            'durations' => config('torneo.match_minutes'),
            'changeover' => config('torneo.changeover_minutes'),
        ]);
    }
}

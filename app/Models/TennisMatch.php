<?php

namespace App\Models;

use App\Services\FormatPlanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Match" es palabra reservada en PHP, de ahí el nombre. */
class TennisMatch extends Model
{
    public const PENDING = 'pending';

    public const PLAYING = 'playing';

    public const FINISHED = 'finished';

    protected $table = 'matches';

    protected $fillable = [
        'tournament_id', 'stage', 'group_id', 'round', 'bracket_size', 'position', 'third_place',
        'pair1_id', 'pair2_id', 'start_games', 'games1', 'games2', 'winner_id', 'status', 'court',
        'queue_order', 'postponed', 'started_at', 'finished_at', 'walkover',
    ];

    protected function casts(): array
    {
        return [
            'third_place' => 'boolean',
            'walkover' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function pair1(): BelongsTo
    {
        return $this->belongsTo(Pair::class, 'pair1_id');
    }

    public function pair2(): BelongsTo
    {
        return $this->belongsTo(Pair::class, 'pair2_id');
    }

    public function isGroup(): bool
    {
        return $this->stage === 'group';
    }

    public function isFinished(): bool
    {
        return $this->status === self::FINISHED;
    }

    public function hasBothPairs(): bool
    {
        return $this->pair1_id && $this->pair2_id;
    }

    public function startGames(): int
    {
        return $this->start_games ?? (int) ($this->isGroup()
            ? $this->tournament->start_games_groups
            : $this->tournament->start_games_knockout);
    }

    public function stageLabel(): string
    {
        if ($this->isGroup()) {
            return 'Grupo '.$this->group->name;
        }

        if ($this->stage === 'consolation') {
            return 'Consolación · '.FormatPlanner::roundName($this->bracket_size);
        }

        if ($this->tournament->qualifiers === 6 && $this->bracket_size === 8) {
            return 'Ronda previa';
        }

        return $this->third_place ? '3er y 4º puesto' : FormatPlanner::roundName($this->bracket_size);
    }

    public function scoreLabel(): string
    {
        if (! $this->isFinished()) {
            return '';
        }

        return $this->walkover ? 'W.O.' : "{$this->games1}-{$this->games2}";
    }
}

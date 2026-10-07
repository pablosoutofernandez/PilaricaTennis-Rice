<?php

namespace App\Models;

use App\Services\FormatPlanner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    public const REGISTRATION = 'registration';

    public const GROUPS = 'groups';

    public const KNOCKOUT = 'knockout';

    public const FINISHED = 'finished';

    protected $attributes = [
        'start_time' => '09:00',
        'end_time' => '20:00',
        'courts' => 2,
        'status' => self::REGISTRATION,
        'consolation_all' => false,
    ];

    protected $fillable = [
        'name', 'date', 'start_time', 'end_time', 'courts', 'status',
        'group_sizes', 'qualifiers', 'start_games_groups', 'start_games_knockout', 'consolation_all',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'group_sizes' => 'array',
            'consolation_all' => 'boolean',
        ];
    }

    public function pairs(): HasMany
    {
        return $this->hasMany(Pair::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class)->orderBy('name');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TennisMatch::class);
    }

    public function availableMinutes(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        return max(0, (int) $start->diffInMinutes($end, false));
    }

    /** Propuesta de formato para las parejas inscritas ahora mismo. */
    public function proposal(): ?array
    {
        $count = $this->pairs()->count();

        if ($count < config('torneo.min_pairs') || $count > config('torneo.max_pairs')) {
            return null;
        }

        return app(FormatPlanner::class)->plan($count, $this->availableMinutes(), $this->courts, $this->consolation_all);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::REGISTRATION => 'Inscripción',
            self::GROUPS => 'Fase de grupos',
            self::KNOCKOUT => 'Fase final',
            self::FINISHED => 'Finalizado',
        };
    }

    public function isRegistration(): bool
    {
        return $this->status === self::REGISTRATION;
    }
}

<?php

namespace App\Models;

use App\Services\FinishEstimator;
use App\Services\FormatPlanner;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'group_sizes', 'qualifiers', 'start_games_groups', 'start_games_knockout', 'consolation_all', 'closed_courts',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'group_sizes' => 'array',
            'consolation_all' => 'boolean',
            'closed_courts' => 'array',
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

    /** El torneo que se destaca en la portada y en el menú: el que se juega; si no, el próximo; si no, el último. */
    public static function featured(): ?self
    {
        return static::whereIn('status', [self::GROUPS, self::KNOCKOUT])->latest('date')->first()
            ?? static::where('status', self::REGISTRATION)->oldest('date')->first()
            ?? static::latest('date')->first();
    }

    /** Usuarios que pueden organizar este torneo (además del administrador). */
    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Nombres de quien organiza, para que el público sepa a quién dirigirse.
     * Sin organizadores asignados, organiza el administrador.
     *
     * @return Collection<int, string>
     */
    public function organizerNames(): Collection
    {
        return $this->organizers()->pluck('name')
            ->whenEmpty(fn () => User::where('is_admin', true)->pluck('name'));
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

    /**
     * Propuesta de formato para las parejas inscritas ahora mismo. Una vez empezado,
     * la del sorteo, aunque luego se hayan retirado o añadido parejas.
     */
    public function proposal(): ?array
    {
        $count = $this->isRegistration() ? $this->pairs()->count() : (int) array_sum($this->group_sizes ?? []);

        if ($count < config('torneo.min_pairs') || $count > config('torneo.max_pairs')) {
            return null;
        }

        return app(FormatPlanner::class)->plan($count, $this->availableMinutes(), $this->courts, $this->consolation_all);
    }

    /**
     * Hora de fin estimada con lo que queda por jugar, o real si ya ha terminado.
     *
     * @return array{finish: \Illuminate\Support\Carbon, scheduled_finish: \Illuminate\Support\Carbon, delay_minutes: int, pace: float, finished: bool, paused: bool, average_minutes: int, average_is_real: bool}|null
     */
    public function finishEstimate(): ?array
    {
        return app(FinishEstimator::class)->estimate($this);
    }

    /** @return int[] pistas que se pueden usar ahora mismo */
    public function openCourts(): array
    {
        return array_values(array_diff(range(1, $this->courts), $this->closed_courts ?? []));
    }

    public function isCourtClosed(int $court): bool
    {
        return in_array($court, $this->closed_courts ?? []);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pair extends Model
{
    /**
     * El número y el puesto de sorteo se asignan al empezar el torneo, con todas las parejas
     * ya apuntadas, para que no dependan del orden de inscripción.
     */
    protected $fillable = ['tournament_id', 'group_id', 'player1', 'player2', 'seeded', 'withdrawn_at', 'number', 'draw_position'];

    protected function casts(): array
    {
        return ['seeded' => 'boolean', 'withdrawn_at' => 'datetime'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    public function getNameAttribute(): string
    {
        return "{$this->player1} / {$this->player2}";
    }
}

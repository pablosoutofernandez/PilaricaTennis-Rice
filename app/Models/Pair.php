<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pair extends Model
{
    protected $fillable = ['tournament_id', 'group_id', 'player1', 'player2', 'seeded'];

    protected function casts(): array
    {
        return ['seeded' => 'boolean'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function getNameAttribute(): string
    {
        return "{$this->player1} / {$this->player2}";
    }
}

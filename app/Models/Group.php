<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['tournament_id', 'name'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function pairs(): HasMany
    {
        return $this->hasMany(Pair::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TennisMatch::class);
    }
}

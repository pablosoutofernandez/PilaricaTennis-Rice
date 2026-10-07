<?php

use App\Livewire\Bracket;
use App\Livewire\Formats;
use App\Livewire\Groups;
use App\Livewire\MatchBoard;
use App\Livewire\Pairs;
use App\Livewire\TournamentIndex;
use App\Models\Tournament;
use Illuminate\Support\Facades\Route;

Route::livewire('/', TournamentIndex::class)->name('home');
Route::livewire('/formatos', Formats::class)->name('formats');

Route::get('/torneos/{tournament}', function (Tournament $tournament) {
    return redirect()->route(match ($tournament->status) {
        Tournament::REGISTRATION => 'tournaments.pairs',
        Tournament::GROUPS => 'tournaments.groups',
        default => 'tournaments.bracket',
    }, $tournament);
})->name('tournaments.show');

Route::livewire('/torneos/{tournament}/parejas', Pairs::class)->name('tournaments.pairs');
Route::livewire('/torneos/{tournament}/grupos', Groups::class)->name('tournaments.groups');
Route::livewire('/torneos/{tournament}/cuadro', Bracket::class)->name('tournaments.bracket');
Route::livewire('/torneos/{tournament}/partidos', MatchBoard::class)->name('tournaments.matches');

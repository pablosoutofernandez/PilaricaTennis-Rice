<?php

use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Bracket;
use App\Livewire\Dashboard;
use App\Livewire\Formats;
use App\Livewire\Groups;
use App\Livewire\MatchBoard;
use App\Livewire\Pairs;
use App\Livewire\TournamentIndex;
use App\Models\Tournament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Dashboard::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::livewire('/entrar', Login::class)->name('login');
    Route::livewire('/registro', Register::class)->name('register');
});

Route::post('/salir', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'can:admin'])->prefix('admin')->group(function () {
    Route::livewire('/', TournamentIndex::class)->name('admin.tournaments');
    Route::livewire('/formatos', Formats::class)->name('formats');
    Route::livewire('/usuarios', Users::class)->name('admin.users');
});

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

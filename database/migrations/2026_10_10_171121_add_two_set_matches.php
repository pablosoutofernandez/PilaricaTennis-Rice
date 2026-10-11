<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partidos a 2 sets + súper tie-break: el marcador inicial -1 los identifica
     * y cada set se guarda aparte.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->tinyInteger('start_games_groups')->nullable()->change();
            $table->tinyInteger('start_games_knockout')->nullable()->change();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->tinyInteger('start_games')->nullable()->change();
            // [[juegos pareja 1, juegos pareja 2], ...]; el tercero, si lo hay, es el súper tie-break.
            $table->json('sets')->nullable()->after('games2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('sets');
            $table->unsignedTinyInteger('start_games')->nullable()->change();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('start_games_groups')->nullable()->change();
            $table->unsignedTinyInteger('start_games_knockout')->nullable()->change();
        });
    }
};

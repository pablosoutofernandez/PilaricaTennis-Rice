<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            // Número de la pareja dentro de su torneo, para anotar a mano.
            $table->unsignedSmallInteger('number')->nullable();
        });

        DB::table('pairs')->orderBy('tournament_id')->orderBy('id')->get(['id', 'tournament_id'])
            ->groupBy('tournament_id')
            ->each(fn ($pairs) => $pairs->values()->each(
                fn ($pair, $index) => DB::table('pairs')->where('id', $pair->id)->update(['number' => $index + 1])
            ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};

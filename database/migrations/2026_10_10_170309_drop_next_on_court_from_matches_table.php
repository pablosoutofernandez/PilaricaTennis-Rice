<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los siguientes partidos ya no tienen pista fija: entran en la primera que quede libre.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('next_on_court');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->unsignedTinyInteger('next_on_court')->nullable();
        });
    }
};

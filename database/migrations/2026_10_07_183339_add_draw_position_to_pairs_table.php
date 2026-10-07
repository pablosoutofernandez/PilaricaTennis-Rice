<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            // Puesto en el sorteo que se hace al empezar: último desempate si todo lo demás es igual.
            $table->unsignedSmallInteger('draw_position')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->dropColumn('draw_position');
        });
    }
};

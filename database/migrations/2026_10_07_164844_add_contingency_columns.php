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
            $table->timestamp('withdrawn_at')->nullable();
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('walkover')->default(false);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->json('closed_courts')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->dropColumn('withdrawn_at');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('walkover');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('closed_courts');
        });
    }
};

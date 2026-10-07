<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->time('start_time')->default('09:00');
            $table->time('end_time')->default('20:00');
            $table->unsignedTinyInteger('courts')->default(2);
            // registration -> groups -> knockout -> finished
            $table->string('status')->default('registration');
            $table->json('group_sizes')->nullable();
            $table->unsignedTinyInteger('qualifiers')->nullable();
            // null = automático (lo decide el planificador al empezar)
            $table->unsignedTinyInteger('start_games_groups')->nullable();
            $table->unsignedTinyInteger('start_games_knockout')->nullable();
            $table->timestamps();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('name', 5);
            $table->timestamps();
        });

        Schema::create('pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('player1');
            $table->string('player2');
            $table->boolean('seeded')->default(false);
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->string('stage'); // group | knockout
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            // En eliminatoria: nº de parejas en la ronda (8 = cuartos) y posición en el cuadro.
            $table->unsignedSmallInteger('bracket_size')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->boolean('third_place')->default(false);
            $table->foreignId('pair1_id')->nullable()->constrained('pairs')->nullOnDelete();
            $table->foreignId('pair2_id')->nullable()->constrained('pairs')->nullOnDelete();
            $table->unsignedTinyInteger('start_games')->nullable();
            $table->unsignedTinyInteger('games1')->nullable();
            $table->unsignedTinyInteger('games2')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('pairs')->nullOnDelete();
            $table->string('status')->default('pending'); // pending | playing | finished
            $table->unsignedTinyInteger('court')->nullable();
            $table->unsignedInteger('queue_order')->default(0);
            $table->unsignedSmallInteger('postponed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'status', 'queue_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('pairs');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('tournaments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game Teams: per-game container for each side of a match.
 *
 * A side is either a PERMANENT team (team_id references teams.id, type=permanent)
 * or a TEMPORARY "pick players" side (team_id NULL, type=temporary) that exists
 * only for this game and is never inserted into the permanent teams table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_teams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');
            $table->enum('type', ['permanent', 'temporary'])->default('temporary');
            $table->enum('side', ['home', 'away']);

            $table->timestamps();

            // One side container per game side.
            $table->unique(['game_id', 'side']);
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_teams');
    }
};

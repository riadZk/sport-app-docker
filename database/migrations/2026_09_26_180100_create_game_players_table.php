<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game players: roster of a specific Game Team for a specific game.
 *
 * A player belongs to a game_team (not just game+side) so the same player can
 * appear in many games with different teams. Selected players start as
 * `pending` and become `joined` only after accepting their request.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Replace the old game_id+side pivot with the game_team-scoped roster.
        Schema::dropIfExists('game_player');

        Schema::create('game_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('game_team_id')->constrained('game_teams')->cascadeOnDelete();
            $table->foreignId('player_stat_id')->constrained('player_stats')->cascadeOnDelete();

            $table->enum('status', ['pending', 'joined', 'declined', 'left'])->default('pending');

            $table->timestamps();

            // A player appears once per game side; may still play the other
            // game of the same day in a different team.
            $table->unique(['game_team_id', 'player_stat_id']);
            $table->index(['game_id', 'player_stat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');

        // Restore the original pivot shape.
        Schema::create('game_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('player_stat_id')->constrained('player_stats')->cascadeOnDelete();
            $table->enum('side', ['home', 'away'])->default('home');
            $table->enum('status', ['joined', 'left'])->default('joined');
            $table->timestamps();
            $table->unique(['game_id', 'player_stat_id']);
        });
    }
};

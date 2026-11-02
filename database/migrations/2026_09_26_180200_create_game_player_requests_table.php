<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Player requests for a game: invitations sent to individually selected
 * players ("I don't want to play with my team / I want to choose players").
 *
 * Mirrors the existing team_invitations concept but is scoped to a Game Team
 * instead of a permanent team (team_invitations.team_id is a hard FK, so it
 * cannot reference temporary game sides).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_player_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('game_team_id')->constrained('game_teams')->cascadeOnDelete();
            $table->foreignId('player_stat_id')->constrained('player_stats')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->index(['game_team_id', 'player_stat_id', 'status']);
            $table->index(['player_stat_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_player_requests');
    }
};

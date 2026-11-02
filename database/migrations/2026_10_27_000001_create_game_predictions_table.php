<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Game Prediction Voting: every authenticated user may predict the outcome
 * of a game exactly once — home win, draw or away win. One vote per user
 * per game, changeable until the game starts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('prediction', ['home_win', 'draw', 'away_win']);
            $table->timestamps();

            // One vote per user per game (a re-vote updates the row).
            $table->unique(['game_id', 'user_id']);
            $table->index('prediction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_predictions');
    }
};

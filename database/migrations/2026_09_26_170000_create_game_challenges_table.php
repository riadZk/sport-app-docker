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
        Schema::create('game_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('challenging_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('challenged_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'status']);
            $table->index(['challenged_team_id', 'status']);
        });

        // Pivot: players explicitly registered on a game (open match / fill-ins).
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_player');
        Schema::dropIfExists('game_challenges');
    }
};

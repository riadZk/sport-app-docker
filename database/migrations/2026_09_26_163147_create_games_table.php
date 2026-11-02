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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Pitch — either a manually created pitch or a booked venue field.
            $table->foreignId('game_pitch_id')->nullable()->constrained('game_pitches')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            // Teams — nullable so the game can start as an Open Match / draft.
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();

            // Settings
            $table->date('game_date');
            $table->time('start_time');
            $table->integer('duration'); // minutes
            $table->enum('game_type', ['5v5', '6v6', '7v7', '8v8', '9v9', '11v11'])->default('5v5');
            $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed');
            $table->enum('privacy', ['public', 'private'])->default('public');

            // draft → scheduled → ongoing → completed, cancelled
            $table->enum('status', ['draft', 'scheduled', 'ongoing', 'completed', 'cancelled'])->default('draft');

            $table->timestamps();

            $table->index(['game_date', 'start_time']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_stat_id')->constrained('player_stats')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();

            // 'invitation' = admin → player  |  'request' = player → team
            $table->enum('type', ['invitation', 'request']);
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending');

            $table->text('message')->nullable();

            $table->timestamps();

            // Only one pending interaction per player_stat + team combination
            $table->index(['team_id', 'player_stat_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};

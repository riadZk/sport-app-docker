<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_id')->constrained('player_stats')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->enum('role', ['player', 'captain'])->default('player');
            $table->string('position')->nullable();
            $table->integer('shirt_number')->nullable();

            $table->enum('status', ['pending', 'active', 'rejected', 'left'])->default('pending');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            $table->unique(['player_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};

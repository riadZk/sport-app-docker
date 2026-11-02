<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_category_id')->constrained('sport_categories');
            $table->string('primary_position');
            $table->string('secondary_position')->nullable();
            $table->enum('prefered_foot', ['left', 'right', 'both'])->nullable();
            $table->integer('match_played')->default(0);
            $table->integer('nbr_goals')->default(0);
            $table->integer('pac')->default(0);
            $table->integer('dribble')->default(0);
            $table->integer('physique')->default(0);
            $table->integer('shoot')->default(0);
            $table->integer('pass')->default(0);
            $table->integer('defence')->default(0);
            $table->integer('assists')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'sport_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};

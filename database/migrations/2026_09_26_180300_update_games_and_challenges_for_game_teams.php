<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-shape games + game_challenges for Game Teams:
 *  - games no longer stores home_team_id / away_team_id (sides live in game_teams)
 *  - challenges reference game_teams (either side may be permanent or temporary)
 *    plus a direct challenged_team_id / challenged_user_id for the offered options.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The home/away team columns may not exist on a fresh database (the
        // games table only ever stored sides via game_teams). Guard the
        // drop so the migration is idempotent across schema histories.
        $dropGameTeamColumns = Schema::hasColumn('games', 'home_team_id')
            && Schema::hasColumn('games', 'away_team_id');

        if ($dropGameTeamColumns) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropForeign(['home_team_id']);
                $table->dropForeign(['away_team_id']);
                $table->dropColumn(['home_team_id', 'away_team_id']);
            });
        }

        Schema::table('game_challenges', function (Blueprint $table) {
            // The legacy index references a column being dropped — SQLite
            // rebuilds the table on column drops, so remove it explicitly first.
            // On MySQL the FK constraints must be dropped before the index
            // that covers their columns, or the index drop is rejected.
            $table->dropForeign(['challenging_team_id']);
            $table->dropForeign(['challenged_team_id']);
            $table->dropIndex(['challenged_team_id', 'status']);
            $table->dropColumn(['challenging_team_id', 'challenged_team_id']);

            // The side sending the challenge (always the home game team).
            $table->foreignId('challenging_game_team_id')->after('game_id')->constrained('game_teams')->cascadeOnDelete();

            // Offered opponent options — at least one must be filled.
            $table->foreignId('challenged_game_team_id')->nullable()->after('challenging_game_team_id')->constrained('game_teams')->nullOnDelete();
            $table->foreignId('challenged_team_id')->nullable()->after('challenged_game_team_id')->constrained('teams')->nullOnDelete();
            $table->foreignId('challenged_user_id')->nullable()->after('challenged_team_id')->constrained('users')->nullOnDelete();

            $table->index(['challenged_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('game_challenges', function (Blueprint $table) {
            $table->dropForeign(['challenging_game_team_id']);
            $table->dropForeign(['challenged_game_team_id']);
            $table->dropForeign(['challenged_team_id']);
            $table->dropForeign(['challenged_user_id']);
            $table->dropIndex(['challenged_user_id', 'status']);
            $table->dropColumn([
                'challenging_game_team_id',
                'challenged_game_team_id',
                'challenged_team_id',
                'challenged_user_id',
            ]);

            $table->foreignId('challenging_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('challenged_team_id')->nullable()->constrained('teams')->nullOnDelete();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'sport_category_id')) {
                $table->foreignId('sport_category_id')->default(1)->after('user_id')->constrained('sport_categories')->cascadeOnDelete();
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $existingIndexes = collect(\DB::select("SHOW INDEX FROM teams WHERE Key_name = 'teams_user_id_sport_category_id_unique'"));
            $indexExists = $existingIndexes->isNotEmpty();
        } else {
            // SQLite / Postgres portable index check.
            $indexExists = ! empty(Schema::getIndexes('teams'));
        }

        Schema::table('teams', function (Blueprint $table) use ($indexExists) {
            if (! $indexExists) {
                $table->unique(['user_id', 'sport_category_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'sport_category_id']);
            $table->dropConstrainedForeignId('sport_category_id');
        });
    }
};

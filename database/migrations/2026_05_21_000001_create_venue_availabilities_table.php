<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_availabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venue_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('day_of_week', [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday',
            ]);

            $table->time('start_time');
            $table->time('end_time');

            // optional seasonal window (e.g. Ramadan hours)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['venue_id', 'day_of_week']);
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_availabilities');
    }
};

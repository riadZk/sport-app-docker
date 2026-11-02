<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_prices', function (Blueprint $table) {
            $table->id();

            // relation with field
            $table->foreignId('field_id')
                ->constrained()
                ->cascadeOnDelete();

            // human-readable label (e.g. "Weekend", "Ramadan", "Peak Hours")
            $table->string('label')->nullable();

            // pricing mode
            $table->enum('pricing_type', [
                'hourly',
                'daily',
            ])->default('hourly');

            // monday, tuesday...
            $table->enum('day_of_week', [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday',
            ])->nullable();

            // time range
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // price
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('DZD');

            // special pricing priority (higher = wins over lower)
            $table->integer('priority')->default(1);

            // special pricing dates (Ramadan / seasonal)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // booking rules
            $table->integer('min_booking_hours')->nullable();
            $table->integer('max_booking_hours')->nullable();

            // booking slots in minutes (30, 60, 90 …)
            $table->integer('slot_duration')->default(60);

            // enable / disable price rule
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // composite index for price-resolution queries
            $table->index(
                ['field_id', 'day_of_week', 'start_time', 'end_time', 'priority'],
                'field_prices_resolution_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_prices');
    }
};

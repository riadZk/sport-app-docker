<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('team_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('duration_minutes');

            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'completed',
            ])->default('pending');

            // snapshot at booking time
            $table->decimal('price_total', 10, 2);
            $table->string('currency', 3)->default('DZD');

            $table->foreignId('field_price_id')
                ->nullable()
                ->constrained('field_prices')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['field_id', 'starts_at', 'ends_at']);
            $table->index(['user_id', 'status']);
            $table->unique(['field_id', 'starts_at'], 'field_bookings_field_start_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_bookings');
    }
};

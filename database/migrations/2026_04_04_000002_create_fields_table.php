<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venue_id')
                ->constrained('venues')
                ->cascadeOnDelete();

            $table->foreignId('field_format_id')
                ->constrained('field_formats')
                ->restrictOnDelete();

            $table->string('name', 100);

            $table->enum('surface_type', ['grass', 'artificial', 'futsal'])
                ->default('artificial');

            $table->decimal('width_m', 5, 2)->nullable();
            $table->decimal('length_m', 5, 2)->nullable();

            $table->boolean('is_indoor')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('venue_id');
            $table->index('field_format_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};

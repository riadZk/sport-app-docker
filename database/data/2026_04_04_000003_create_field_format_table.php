<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_format', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('format_id')->constrained()->cascadeOnDelete();

            $table->decimal('price_per_hour', 8, 2);

            $table->unique(['field_id', 'format_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_format');
    }
};

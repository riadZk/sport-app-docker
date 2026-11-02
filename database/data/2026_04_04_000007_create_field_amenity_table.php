<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_amenity', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();

            $table->unique(['field_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_amenity');
    }
};

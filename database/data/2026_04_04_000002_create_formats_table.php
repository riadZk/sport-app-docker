<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formats', function (Blueprint $table) {
            $table->id();

            $table->string('name'); // 5v5, 6v6
            $table->unsignedTinyInteger('players_per_team');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formats');
    }
};

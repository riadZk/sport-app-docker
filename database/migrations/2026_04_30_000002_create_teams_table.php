<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('name');
            $table->string('short_name', 10);
            $table->string('city', 100);
            $table->string('address', 255)->nullable();

            // Logo Handling
            $table->enum('logo_type', ['default', 'upload'])->default('default');
            $table->string('default_logo_id')->nullable();

            // Colors
            $table->string('primary_color', 7)->default('#4169E1');
            $table->string('secondary_color', 7)->default('#FFD700');

            // Game Setup
            $table->enum('match_type', ['5v5', '6v6', '7v7', '8v8', '9v9', '11v11'])->default('11v11');
            $table->string('formation', 20)->default('4-3-3');

            // Owner
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};

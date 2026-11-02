<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('challenge_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
                $table->string('action');
                $table->foreignId('user_id')->constrained();
                $table->text('note')->nullable();
                $table->string('status');
                $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenge_histories');
    }
};

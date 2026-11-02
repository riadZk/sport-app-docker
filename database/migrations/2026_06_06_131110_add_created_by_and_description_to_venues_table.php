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
        Schema::table('venues', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['description', 'created_by']);
        });
    }
};

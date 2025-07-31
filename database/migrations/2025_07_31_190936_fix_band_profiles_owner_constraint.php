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
        Schema::table('band_profiles', function (Blueprint $table) {
            // Drop the incorrect foreign key constraint
            $table->dropForeign(['owner_id']);

            // Add the correct foreign key constraint to users table
            $table->foreign('owner_id')->nullable()->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_profiles', function (Blueprint $table) {
            // Drop the corrected foreign key
            $table->dropForeign(['owner_id']);

            // Restore the incorrect constraint (for rollback purposes)
            $table->foreignId('owner_id')->constrained()->onDelete('cascade');
        });
    }
};

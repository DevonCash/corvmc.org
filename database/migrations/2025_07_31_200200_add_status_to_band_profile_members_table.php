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
        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->enum('status', ['active', 'invited', 'declined'])
                  ->default('active')
                  ->after('name');
            $table->timestamp('invited_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->dropColumn(['status', 'invited_at']);
        });
    }
};

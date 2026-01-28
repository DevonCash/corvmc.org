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
            $table->unique(['band_profile_id', 'user_id'], 'band_profile_members_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_profile_members', function (Blueprint $table) {
            $table->dropUnique('band_profile_members_unique');
        });
    }
};

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
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->json('embeds')->nullable()->after('links');
        });

        Schema::table('band_profiles', function (Blueprint $table) {
            $table->json('embeds')->nullable()->after('links');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropColumn('embeds');
        });

        Schema::table('band_profiles', function (Blueprint $table) {
            $table->dropColumn('embeds');
        });
    }
};

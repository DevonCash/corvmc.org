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
        Schema::table('users', function (Blueprint $table) {
            // Drop index first before dropping column
            $table->dropIndex(['community_event_trust_points']);
            $table->dropColumn('trust_points');
            $table->dropColumn('community_event_trust_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('trust_points')->nullable();
            $table->integer('community_event_trust_points')->default(0);
            $table->index('community_event_trust_points');
        });
    }
};

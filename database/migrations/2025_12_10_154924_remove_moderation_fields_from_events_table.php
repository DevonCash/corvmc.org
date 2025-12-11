<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove deprecated moderation fields from events table.
     * These fields were superseded by the Reports & Revisions system.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['moderation_reviewed_by']);
            $table->dropColumn([
                'moderation_status',
                'moderation_reviewed_at',
                'moderation_reviewed_by',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('moderation_status')->default('pending');
            $table->timestamp('moderation_reviewed_at')->nullable();
            $table->foreignId('moderation_reviewed_by')->nullable()->constrained('users');
        });
    }
};

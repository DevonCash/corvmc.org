<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('moderation_status')->default('approved')->after('status');
            $table->timestamp('moderation_reviewed_at')->nullable()->after('moderation_status');
            $table->foreignId('moderation_reviewed_by')->nullable()->constrained('users')->after('moderation_reviewed_at');
        });

        // Migrate existing data: if status is 'approved', set moderation_status to 'approved'
        // Otherwise set to 'pending' for review
        DB::table('events')->update([
            'moderation_status' => DB::raw("CASE WHEN status = 'approved' THEN 'approved' ELSE 'pending' END"),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['moderation_reviewed_by']);
            $table->dropColumn(['moderation_status', 'moderation_reviewed_at', 'moderation_reviewed_by']);
        });
    }
};

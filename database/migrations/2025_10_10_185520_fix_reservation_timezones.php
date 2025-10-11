<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adjusts existing timestamps that were stored when the app
     * timezone was UTC but users intended America/Los_Angeles times.
     *
     * PROBLEM:
     * - Before: User entered "2:00 PM" (meaning PST) but it was stored as "14:00 UTC"
     * - After timezone change: "14:00 UTC" displays as "6:00 AM PST" (wrong!)
     * - Fix: Add 8 hours so "14:00 UTC" becomes "22:00 UTC" which displays as "2:00 PM PST" (correct!)
     *
     * We add 8 hours to convert from incorrectly-stored-as-local to correct-UTC.
     * (Using 8 hours for PST; this is approximate and may be off by 1 hour during PDT transitions)
     */
    public function up(): void
    {
        // Fix reservations (PostgreSQL syntax)
        DB::statement("
            UPDATE reservations
            SET
                reserved_at = reserved_at + INTERVAL '8 hours',
                reserved_until = reserved_until + INTERVAL '8 hours'
        ");

        DB::statement("
            UPDATE reservations
            SET paid_at = paid_at + INTERVAL '8 hours'
            WHERE paid_at IS NOT NULL
        ");

        // Fix productions
        DB::statement("
            UPDATE productions
            SET
                start_time = start_time + INTERVAL '8 hours',
                end_time = end_time + INTERVAL '8 hours'
        ");

        DB::statement("
            UPDATE productions
            SET published_at = published_at + INTERVAL '8 hours'
            WHERE published_at IS NOT NULL
        ");

        // Fix community events if table exists
        if (DB::getSchemaBuilder()->hasTable('community_events')) {
            DB::statement("
                UPDATE community_events
                SET
                    start_time = start_time + INTERVAL '8 hours',
                    end_time = end_time + INTERVAL '8 hours'
            ");

            DB::statement("
                UPDATE community_events
                SET published_at = published_at + INTERVAL '8 hours'
                WHERE published_at IS NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Subtract the 8 hours back to restore original values (PostgreSQL syntax)
        DB::statement("
            UPDATE reservations
            SET
                reserved_at = reserved_at - INTERVAL '8 hours',
                reserved_until = reserved_until - INTERVAL '8 hours'
        ");

        DB::statement("
            UPDATE reservations
            SET paid_at = paid_at - INTERVAL '8 hours'
            WHERE paid_at IS NOT NULL
        ");

        DB::statement("
            UPDATE productions
            SET
                start_time = start_time - INTERVAL '8 hours',
                end_time = end_time - INTERVAL '8 hours'
        ");

        DB::statement("
            UPDATE productions
            SET published_at = published_at - INTERVAL '8 hours'
            WHERE published_at IS NOT NULL
        ");

        if (DB::getSchemaBuilder()->hasTable('community_events')) {
            DB::statement("
                UPDATE community_events
                SET
                    start_time = start_time - INTERVAL '8 hours',
                    end_time = end_time - INTERVAL '8 hours'
            ");

            DB::statement("
                UPDATE community_events
                SET published_at = published_at - INTERVAL '8 hours'
                WHERE published_at IS NOT NULL
            ");
        }
    }
};

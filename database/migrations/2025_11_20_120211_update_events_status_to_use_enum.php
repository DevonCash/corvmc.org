<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing status values to new enum values
        // Old: 'approved', 'pre-production', 'completed', 'cancelled', etc.
        // New: 'scheduled', 'cancelled', 'postponed', 'at_capacity'

        DB::table('events')->update([
            'status' => DB::raw("
                CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    ELSE 'scheduled'
                END
            "),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old status values
        DB::table('events')->update([
            'status' => DB::raw("
                CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    ELSE 'approved'
                END
            "),
        ]);
    }
};

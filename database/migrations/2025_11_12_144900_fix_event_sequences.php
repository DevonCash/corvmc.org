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
        // Only run on PostgreSQL (not SQLite for tests)
        if (DB::getDriverName() === 'pgsql') {
            // Fix the sequence for events table after migration
            DB::statement("SELECT setval('events_id_seq', (SELECT MAX(id) FROM events))");

            // Fix the sequence for event_bands table after migration
            DB::statement("SELECT setval('event_bands_id_seq', (SELECT MAX(id) FROM event_bands))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible
    }
};

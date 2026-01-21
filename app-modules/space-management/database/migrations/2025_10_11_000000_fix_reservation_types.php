<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes reservation type values for existing reservations
     * that were created before the Single Table Inheritance (STI) implementation.
     *
     * Old reservations have type = 'App\Models\Reservation' but should be:
     * - 'App\Models\RehearsalReservation' for user-owned practice space reservations
     * - 'App\Models\ProductionReservation' for production-owned space reservations
     */
    public function up(): void
    {
        // Update user-owned reservations to RehearsalReservation
        DB::table('reservations')
            ->where('type', 'App\Models\Reservation')
            ->where('reservable_type', 'App\Models\User')
            ->update(['type' => 'App\Models\RehearsalReservation']);

        // Update production-owned reservations to ProductionReservation
        DB::table('reservations')
            ->where('type', 'App\Models\Reservation')
            ->where('reservable_type', 'App\Models\Production')
            ->update(['type' => 'App\Models\ProductionReservation']);

        // Update any remaining base Reservation types to RehearsalReservation
        // (these are likely legacy data that should be practice space reservations)
        DB::table('reservations')
            ->where('type', 'App\Models\Reservation')
            ->update(['type' => 'App\Models\RehearsalReservation']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert all specific types back to base Reservation
        DB::table('reservations')
            ->whereIn('type', [
                'App\Models\RehearsalReservation',
                'App\Models\ProductionReservation',
            ])
            ->update(['type' => 'App\Models\Reservation']);
    }
};

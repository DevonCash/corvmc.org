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
        // Step 1: Rename recurring_reservations to recurring_series
        Schema::rename('recurring_reservations', 'recurring_series');

        // Step 2: Add recurable_type column to track what type of instances this creates
        Schema::table('recurring_series', function (Blueprint $table) {
            $table->string('recurable_type')->after('user_id')->default('App\\Models\\Reservation');
            $table->index(['recurable_type', 'status']);
        });

        // Step 3: Update reservations table - rename foreign key
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['recurring_reservation_id']);
            $table->dropIndex(['recurring_reservation_id', 'instance_date']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('recurring_reservation_id', 'recurring_series_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('recurring_series_id')->references('id')->on('recurring_series')->nullOnDelete();
            $table->index(['recurring_series_id', 'instance_date']);
        });

        // Step 4: Add recurring_series_id to events table
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('recurring_series_id')->nullable()->after('organizer_id')->constrained('recurring_series')->nullOnDelete();
            $table->date('instance_date')->nullable()->after('recurring_series_id');
            $table->index(['recurring_series_id', 'instance_date']);
            $table->index('instance_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove from events
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['recurring_series_id']);
            $table->dropIndex(['recurring_series_id', 'instance_date']);
            $table->dropIndex(['instance_date']);
            $table->dropColumn(['recurring_series_id', 'instance_date']);
        });

        // Revert reservations table
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['recurring_series_id']);
            $table->dropIndex(['recurring_series_id', 'instance_date']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('recurring_series_id', 'recurring_reservation_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('recurring_reservation_id')->references('id')->on('recurring_series')->nullOnDelete();
            $table->index(['recurring_reservation_id', 'instance_date']);
        });

        // Remove recurable_type from recurring_series
        Schema::table('recurring_series', function (Blueprint $table) {
            $table->dropIndex(['recurable_type', 'status']);
            $table->dropColumn('recurable_type');
        });

        // Rename back to recurring_reservations
        Schema::rename('recurring_series', 'recurring_reservations');
    }
};

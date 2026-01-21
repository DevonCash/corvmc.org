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
        Schema::create('recurring_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Pattern definition
            $table->string('recurrence_rule'); // iCal RRULE format
            $table->time('start_time'); // e.g., '19:00:00'
            $table->time('end_time'); // e.g., '21:00:00'
            $table->integer('duration_minutes'); // Redundant but helpful

            // Series metadata
            $table->date('series_start_date'); // When series begins
            $table->date('series_end_date')->nullable(); // NULL = indefinite
            $table->integer('max_advance_days')->default(90); // How far ahead to generate

            // Status
            $table->string('status', 20)->default('active'); // 'active', 'paused', 'cancelled', 'completed'

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['status', 'series_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_reservations');
    }
};

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
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('recurring_reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->date('instance_date')->nullable(); // The date this instance represents
            $table->string('cancellation_reason', 100)->nullable(); // Reason for cancellation

            // Indexes
            $table->index(['recurring_reservation_id', 'instance_date']);
            $table->index('instance_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['recurring_reservation_id']);
            $table->dropIndex(['recurring_reservation_id', 'instance_date']);
            $table->dropIndex(['instance_date']);
            $table->dropColumn(['recurring_reservation_id', 'instance_date', 'cancellation_reason']);
        });
    }
};

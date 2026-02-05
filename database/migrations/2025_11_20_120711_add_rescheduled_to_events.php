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
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('rescheduled_to_id')
                ->nullable()
                ->after('status')
                ->constrained('events')
                ->nullOnDelete();

            $table->text('reschedule_reason')->nullable()->after('rescheduled_to_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['rescheduled_to_id']);
            $table->dropColumn(['rescheduled_to_id', 'reschedule_reason']);
        });
    }
};

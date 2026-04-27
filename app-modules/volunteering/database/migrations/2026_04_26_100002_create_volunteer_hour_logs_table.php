<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_hour_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('shift_id')->nullable()->constrained('volunteer_shifts');
            $table->foreignId('position_id')->nullable()->constrained('volunteer_positions');
            $table->string('status')->default('interested');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['shift_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('status');

        });

        // Check constraint: exactly one of shift_id or position_id must be non-null.
        // SQLite doesn't support ALTER TABLE ADD CONSTRAINT, so use driver-specific SQL.
        if (DB::getDriverName() === 'sqlite') {
            // SQLite requires recreating the table to add constraints, but we can
            // enforce this at the application layer in dev. The partial unique index
            // below still provides some protection.
        } else {
            DB::statement('
                ALTER TABLE volunteer_hour_logs
                ADD CONSTRAINT volunteer_hour_logs_shift_or_position_check
                CHECK (
                    (shift_id IS NOT NULL AND position_id IS NULL)
                    OR (shift_id IS NULL AND position_id IS NOT NULL)
                )
            ');
        }

        // Partial unique index: prevent double-signup for same shift
        // (only for non-terminal statuses)
        DB::statement('
            CREATE UNIQUE INDEX volunteer_hour_logs_user_shift_active
            ON volunteer_hour_logs (user_id, shift_id)
            WHERE shift_id IS NOT NULL
            AND status NOT IN (\'released\', \'checked_out\')
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_hour_logs');
    }
};

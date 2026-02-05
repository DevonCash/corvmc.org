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
        // Delete non-CMC members (where user_id IS NULL)
        DB::table('band_profile_members')->whereNull('user_id')->delete();

        // Delete any declined invitations (no longer tracking this state)
        DB::table('band_profile_members')->where('status', 'declined')->delete();

        $driver = Schema::connection(null)->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite requires dropping indexes before dropping columns
            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->dropIndex('idx_band_members_status');
            });
        }

        Schema::table('band_profile_members', function (Blueprint $table) {
            // Make user_id required (CMC members only)
            $table->foreignId('user_id')->nullable(false)->change();

            // Remove 'name' column - always use User->name
            $table->dropColumn('name');

            // Drop status column (will recreate next)
            $table->dropColumn('status');
        });

        // Recreate status column with new constraints
        $driver = Schema::connection(null)->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->string('status')->default('active')->after('user_id');
                $table->index('status', 'idx_band_members_status');
            });
        } else {
            // PostgreSQL with CHECK constraint
            DB::statement("ALTER TABLE band_profile_members ADD COLUMN status VARCHAR(255) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'invited'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::connection(null)->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->dropIndex('idx_band_members_status');
            });
        }

        Schema::table('band_profile_members', function (Blueprint $table) {
            // Drop status column
            $table->dropColumn('status');
        });

        // Recreate with original enum values
        if ($driver === 'sqlite') {
            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->string('status')->default('active')->after('user_id');
                $table->index('status', 'idx_band_members_status');
            });
        } else {
            DB::statement("ALTER TABLE band_profile_members ADD COLUMN status VARCHAR(255) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'invited', 'declined'))");
        }

        Schema::table('band_profile_members', function (Blueprint $table) {
            // Restore nullable user_id
            $table->foreignId('user_id')->nullable()->change();

            // Restore name column
            $table->string('name')->nullable()->after('user_id');
        });
    }
};

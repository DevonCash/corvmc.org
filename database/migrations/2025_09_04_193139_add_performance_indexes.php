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
        // Reservations performance indexes
        Schema::table('reservations', function (Blueprint $table) {
            // For conflict detection queries (getConflictingReservations)
            $table->index(['reserved_at', 'reserved_until', 'status'], 'idx_reservations_conflict_detection');
            
            // For user statistics and free hours calculations
            $table->index(['user_id', 'reserved_at'], 'idx_reservations_user_date');
            
            // For monthly free hours queries
            $table->index(['user_id', 'created_at'], 'idx_reservations_user_created');
        });

        // Transactions table indexes removed (Transaction model removed)

        // Productions performance indexes
        Schema::table('productions', function (Blueprint $table) {
            // For conflict detection and upcoming events
            $table->index(['start_time', 'end_time'], 'idx_productions_time_range');
            
            // For published upcoming events (UpcomingEventsWidget)
            $table->index(['published_at', 'start_time'], 'idx_productions_published_upcoming');
        });

        // Model roles performance (if table exists)
        if (Schema::hasTable('model_has_roles')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                // For role checking (isSustainingMember, permission checks)
                if (!Schema::hasIndex('model_has_roles', 'idx_model_has_roles_lookup')) {
                    $table->index(['model_id', 'model_type'], 'idx_model_has_roles_lookup');
                }
            });
        }

        // Band profile members performance
        if (Schema::hasTable('band_profile_members')) {
            Schema::table('band_profile_members', function (Blueprint $table) {
                // For band membership queries
                $table->index(['user_id', 'band_profile_id'], 'idx_band_members_lookup');
                $table->index(['band_profile_id', 'status'], 'idx_band_members_status');
            });
        }

        // Member profiles performance
        Schema::table('member_profiles', function (Blueprint $table) {
            // For visibility filtering and user lookups
            $table->index(['user_id', 'visibility'], 'idx_member_profiles_user_visibility');
            $table->index(['visibility'], 'idx_member_profiles_visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_conflict_detection');
            $table->dropIndex('idx_reservations_user_date');
            $table->dropIndex('idx_reservations_user_created');
        });

        // Transactions table indexes removed (Transaction model removed)

        Schema::table('productions', function (Blueprint $table) {
            $table->dropIndex('idx_productions_time_range');
            $table->dropIndex('idx_productions_published_upcoming');
        });

        if (Schema::hasTable('model_has_roles')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                if (Schema::hasIndex('model_has_roles', 'idx_model_has_roles_lookup')) {
                    $table->dropIndex('idx_model_has_roles_lookup');
                }
            });
        }

        if (Schema::hasTable('band_profile_members')) {
            Schema::table('band_profile_members', function (Blueprint $table) {
                $table->dropIndex('idx_band_members_lookup');
                $table->dropIndex('idx_band_members_status');
            });
        }

        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_member_profiles_user_visibility');
            $table->dropIndex('idx_member_profiles_visibility');
        });
    }
};

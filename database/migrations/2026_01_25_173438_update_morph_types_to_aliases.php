<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map of old class names to new morph aliases.
     */
    private array $morphMap = [
        // Core models
        'App\\Models\\User' => 'user',
        'App\\Models\\StaffProfile' => 'staff_profile',
        'App\\Models\\Invitation' => 'invitation',
        'App\\Models\\PromoCode' => 'promo_code',

        // Band models
        'App\\Models\\Band' => 'band',
        'App\\Models\\BandMember' => 'band_member',

        // Member profile
        'App\\Models\\MemberProfile' => 'member_profile',

        // Event models
        'CorvMC\\Events\\Models\\Event' => 'event',
        'App\\Models\\Event' => 'event', // Legacy
        'CorvMC\\Events\\Models\\Venue' => 'venue',
        'App\\Models\\EventReservation' => 'event_reservation',

        // Space management models
        'CorvMC\\SpaceManagement\\Models\\Reservation' => 'reservation',
        'App\\Models\\Reservation' => 'reservation', // Legacy
        'CorvMC\\SpaceManagement\\Models\\RehearsalReservation' => 'rehearsal_reservation',
        'App\\Models\\RehearsalReservation' => 'rehearsal_reservation', // Legacy
        'CorvMC\\Support\\Models\\RecurringSeries' => 'recurring_series',
        'App\\Models\\RecurringSeries' => 'recurring_series', // Legacy

        // Equipment models
        'CorvMC\\Equipment\\Models\\Equipment' => 'equipment',
        'CorvMC\\Equipment\\Models\\EquipmentLoan' => 'equipment_loan',
        'CorvMC\\Equipment\\Models\\EquipmentDamageReport' => 'equipment_damage_report',

        // Finance models
        'CorvMC\\Finance\\Models\\Charge' => 'charge',
        'CorvMC\\Finance\\Models\\Subscription' => 'subscription',
        'App\\Models\\Subscription' => 'subscription', // Legacy

        // Moderation models
        'CorvMC\\Moderation\\Models\\Report' => 'report',
        'CorvMC\\Moderation\\Models\\Revision' => 'revision',
    ];

    /**
     * Tables and their morph type columns to update.
     */
    private array $tables = [
        'reservations' => ['type', 'reservable_type'],
        'reports' => ['reportable_type'],
        'revisions' => ['revisionable_type'],
        'charges' => ['chargeable_type'],
        'activity_log' => ['subject_type', 'causer_type'],
        'media' => ['model_type'],
        'taggables' => ['taggable_type'],
        'flaggables' => ['flaggable_type'],
        'model_has_permissions' => ['model_type'],
        'model_has_roles' => ['model_type'],
        'recurring_series' => ['recurable_type'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach ($this->morphMap as $oldClass => $newAlias) {
                    DB::table($table)
                        ->where($column, $oldClass)
                        ->update([$column => $newAlias]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Create reverse map
        $reverseMap = array_flip($this->morphMap);

        foreach ($this->tables as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach ($reverseMap as $alias => $oldClass) {
                    DB::table($table)
                        ->where($column, $alias)
                        ->update([$column => $oldClass]);
                }
            }
        }
    }
};

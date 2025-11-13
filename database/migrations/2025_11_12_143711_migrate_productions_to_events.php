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
        if (! DB::table('productions')->exists()) {
            return;
        }

        // Migrate productions to events
        DB::table('productions')->orderBy('id')->chunk(100, function ($productions) {
            foreach ($productions as $production) {
                DB::table('events')->insert([
                    'id' => $production->id,
                    'title' => $production->title,
                    'description' => $production->description,
                    'start_time' => $production->start_time,
                    'end_time' => $production->end_time,
                    'doors_time' => $production->doors_time,
                    'location' => $production->location,
                    'event_link' => $production->ticket_url,
                    'ticket_url' => $production->ticket_url,
                    'ticket_price' => $production->ticket_price,
                    'published_at' => $production->published_at,
                    'organizer_id' => null, // Staff events have no organizer
                    'status' => 'approved', // All existing productions are approved
                    'visibility' => 'public',
                    'event_type' => null,
                    'distance_from_corvallis' => null,
                    'trust_points' => 0,
                    'auto_approved' => false,
                    'created_at' => $production->created_at,
                    'updated_at' => $production->updated_at,
                    'deleted_at' => $production->deleted_at,
                ]);
            }
        });

        // Migrate production_bands to event_bands
        if (DB::table('production_bands')->exists()) {
            DB::table('production_bands')->orderBy('id')->chunk(100, function ($pivots) {
                foreach ($pivots as $pivot) {
                    DB::table('event_bands')->insert([
                        'id' => $pivot->id,
                        'event_id' => $pivot->production_id,
                        'band_profile_id' => $pivot->band_profile_id,
                        'order' => $pivot->order,
                        'set_length' => $pivot->set_length,
                        'created_at' => $pivot->created_at,
                        'updated_at' => $pivot->updated_at,
                    ]);
                }
            });
        }

        // Update polymorphic reservations from Production to Event
        DB::table('reservations')
            ->where('reservable_type', 'App\\Models\\Production')
            ->update(['reservable_type' => 'App\\Models\\Event']);

        // Migrate media from productions to events (if table exists)
        if (DB::getSchemaBuilder()->hasTable('media')) {
            DB::table('media')
                ->where('model_type', 'App\\Models\\Production')
                ->update(['model_type' => 'App\\Models\\Event']);
        }

        // Migrate tags from productions to events (if table exists)
        if (DB::getSchemaBuilder()->hasTable('taggables')) {
            DB::table('taggables')
                ->where('taggable_type', 'App\\Models\\Production')
                ->update(['taggable_type' => 'App\\Models\\Event']);
        }

        // Migrate flags from productions to events (if table exists)
        if (DB::getSchemaBuilder()->hasTable('model_has_flags')) {
            DB::table('model_has_flags')
                ->where('model_type', 'App\\Models\\Production')
                ->update(['model_type' => 'App\\Models\\Event']);
        }

        // Migrate activity logs (if table exists)
        if (DB::getSchemaBuilder()->hasTable('activity_log')) {
            DB::table('activity_log')
                ->where('subject_type', 'App\\Models\\Production')
                ->update(['subject_type' => 'App\\Models\\Event']);

            DB::table('activity_log')
                ->where('causer_type', 'App\\Models\\Production')
                ->update(['causer_type' => 'App\\Models\\Event']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible since we're replacing the system
        // If you need to rollback, restore from backup
    }
};

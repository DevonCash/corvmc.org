<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Clean up any remaining database references to the deprecated CommunityEvent model.
     * The community_events table was dropped in migration 2025_11_12_143826, but polymorphic
     * references may still exist in related tables.
     */
    public function up(): void
    {
        $communityEventType = 'App\\Models\\CommunityEvent';
        $deletedCount = [];

        DB::transaction(function () use ($communityEventType, &$deletedCount) {
            // 1. Clean up activity_log table (Spatie Activity Log)
            if (Schema::hasTable('activity_log')) {
                $count = DB::table('activity_log')
                    ->where('subject_type', $communityEventType)
                    ->orWhere('causer_type', $communityEventType)
                    ->count();

                if ($count > 0) {
                    DB::table('activity_log')
                        ->where('subject_type', $communityEventType)
                        ->orWhere('causer_type', $communityEventType)
                        ->delete();
                    $deletedCount['activity_log'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from activity_log");
                }
            }

            // 2. Clean up reports table (content moderation)
            if (Schema::hasTable('reports')) {
                $count = DB::table('reports')
                    ->where('reportable_type', $communityEventType)
                    ->count();

                if ($count > 0) {
                    DB::table('reports')
                        ->where('reportable_type', $communityEventType)
                        ->delete();
                    $deletedCount['reports'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from reports");
                }
            }

            // 3. Clean up revisions table (content approval workflow)
            if (Schema::hasTable('revisions')) {
                $count = DB::table('revisions')
                    ->where('revisionable_type', $communityEventType)
                    ->count();

                if ($count > 0) {
                    DB::table('revisions')
                        ->where('revisionable_type', $communityEventType)
                        ->delete();
                    $deletedCount['revisions'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from revisions");
                }
            }

            // 4. Clean up model_has_flags table (Spatie Model Flags)
            if (Schema::hasTable('model_has_flags')) {
                $count = DB::table('model_has_flags')
                    ->where('model_type', $communityEventType)
                    ->count();

                if ($count > 0) {
                    DB::table('model_has_flags')
                        ->where('model_type', $communityEventType)
                        ->delete();
                    $deletedCount['model_has_flags'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from model_has_flags");
                }
            }

            // 5. Clean up taggables table (Spatie Tags)
            if (Schema::hasTable('taggables')) {
                $count = DB::table('taggables')
                    ->where('taggable_type', $communityEventType)
                    ->count();

                if ($count > 0) {
                    DB::table('taggables')
                        ->where('taggable_type', $communityEventType)
                        ->delete();
                    $deletedCount['taggables'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from taggables");
                }
            }

            // 6. Clean up media table (Spatie Media Library)
            if (Schema::hasTable('media')) {
                $count = DB::table('media')
                    ->where('model_type', $communityEventType)
                    ->count();

                if ($count > 0) {
                    DB::table('media')
                        ->where('model_type', $communityEventType)
                        ->delete();
                    $deletedCount['media'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from media");
                }
            }

            // 7. Clean up notifications table (database notifications)
            if (Schema::hasTable('notifications')) {
                $count = DB::table('notifications')
                    ->where('type', 'LIKE', '%CommunityEvent%')
                    ->orWhereRaw("data->>'type' = ?", ['community_event'])
                    ->count();

                if ($count > 0) {
                    DB::table('notifications')
                        ->where('type', 'LIKE', '%CommunityEvent%')
                        ->orWhereRaw("data->>'type' = ?", ['community_event'])
                        ->delete();
                    $deletedCount['notifications'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from notifications");
                }
            }

            // 8. Clean up trust_transactions table
            if (Schema::hasTable('trust_transactions')) {
                $count = DB::table('trust_transactions')
                    ->where('content_type', 'App\\Models\\CommunityEvent')
                    ->orWhere('content_type', 'community_events')
                    ->count();

                if ($count > 0) {
                    DB::table('trust_transactions')
                        ->where('content_type', 'App\\Models\\CommunityEvent')
                        ->orWhere('content_type', 'community_events')
                        ->delete();
                    $deletedCount['trust_transactions'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from trust_transactions");
                }
            }

            // 9. Clean up user_trust_balances table
            if (Schema::hasTable('user_trust_balances')) {
                $count = DB::table('user_trust_balances')
                    ->where('content_type', 'App\\Models\\CommunityEvent')
                    ->orWhere('content_type', 'community_events')
                    ->count();

                if ($count > 0) {
                    DB::table('user_trust_balances')
                        ->where('content_type', 'App\\Models\\CommunityEvent')
                        ->orWhere('content_type', 'community_events')
                        ->delete();
                    $deletedCount['user_trust_balances'] = $count;
                    Log::info("Deleted {$count} CommunityEvent references from user_trust_balances");
                }
            }
        });

        // Log summary
        if (empty($deletedCount)) {
            Log::info('CommunityEvent cleanup: No references found - database is already clean');
            echo "✅ No CommunityEvent references found in database\n";
        } else {
            $total = array_sum($deletedCount);
            Log::info("CommunityEvent cleanup complete: Deleted {$total} total references", $deletedCount);
            echo "✅ Cleaned up {$total} CommunityEvent references:\n";
            foreach ($deletedCount as $table => $count) {
                echo "   - {$table}: {$count} rows\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * This migration is destructive and cannot be reversed.
     */
    public function down(): void
    {
        Log::warning('CommunityEvent cleanup migration cannot be reversed - data was permanently deleted');
        echo "⚠️  This migration cannot be reversed - deleted data is not recoverable\n";
    }
};

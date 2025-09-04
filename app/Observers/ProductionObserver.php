<?php

namespace App\Observers;

use App\Models\Production;
use Illuminate\Support\Facades\Cache;

class ProductionObserver
{
    /**
     * Handle the Production "created" event.
     */
    public function created(Production $production): void
    {
        $this->clearProductionCaches($production);
    }

    /**
     * Handle the Production "updated" event.
     */
    public function updated(Production $production): void
    {
        $this->clearProductionCaches($production);
        
        // If production date changed, clear both old and new date caches
        if ($production->isDirty('start_time')) {
            $originalDate = $production->getOriginal('start_time');
            if ($originalDate) {
                Cache::forget("productions.conflicts." . date('Y-m-d', strtotime($originalDate)));
            }
        }
    }

    /**
     * Handle the Production "deleted" event.
     */
    public function deleted(Production $production): void
    {
        $this->clearProductionCaches($production);
    }

    /**
     * Clear all caches related to a production.
     */
    private function clearProductionCaches(Production $production): void
    {
        // Clear upcoming events caches
        Cache::forget('upcoming_events');
        // Clear all user-specific upcoming events caches (wildcard not supported, so we'll clear on next load)
        
        // Clear conflict detection cache for the production date
        if ($production->start_time) {
            $date = $production->start_time->format('Y-m-d');
            Cache::forget("productions.conflicts.{$date}");
        }
        
        // Clear manager's user stats if they have managed productions
        if ($production->manager_id) {
            Cache::forget("user_stats.{$production->manager_id}");
        }
    }
}
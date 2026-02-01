<?php

namespace App\Observers;

use CorvMC\SpaceManagement\Models\SpaceClosure;
use Illuminate\Support\Facades\Cache;

class SpaceClosureObserver
{
    /**
     * Handle the SpaceClosure "created" event.
     */
    public function created(SpaceClosure $closure): void
    {
        $this->clearClosureCaches($closure);
    }

    /**
     * Handle the SpaceClosure "updated" event.
     */
    public function updated(SpaceClosure $closure): void
    {
        $this->clearClosureCaches($closure);

        // If closure dates changed, clear both old and new date caches
        if ($closure->isDirty('starts_at')) {
            $originalDate = $closure->getOriginal('starts_at');
            if ($originalDate) {
                Cache::forget('closures.conflicts.'.date('Y-m-d', strtotime($originalDate)));
            }
        }
    }

    /**
     * Handle the SpaceClosure "deleted" event.
     */
    public function deleted(SpaceClosure $closure): void
    {
        $this->clearClosureCaches($closure);
    }

    /**
     * Clear all caches related to a closure.
     */
    private function clearClosureCaches(SpaceClosure $closure): void
    {
        if ($closure->starts_at) {
            $date = $closure->starts_at->format('Y-m-d');
            Cache::forget("closures.conflicts.{$date}");
        }

        if ($closure->ends_at && ! $closure->starts_at?->isSameDay($closure->ends_at)) {
            $date = $closure->ends_at->format('Y-m-d');
            Cache::forget("closures.conflicts.{$date}");
        }
    }
}

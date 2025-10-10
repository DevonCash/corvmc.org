<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Notifications\ProductionCancelledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteProduction
{
    use AsAction;

    /**
     * Delete a production.
     */
    public function handle(Production $production): bool
    {
        return DB::transaction(function () use ($production) {
            // Notify performers and manager
            $users = GetInterestedUsers::run($production);
            if ($users->isNotEmpty()) {
                Notification::send($users, new ProductionCancelledNotification($production, null));
            }

            return $production->delete();
        });
    }
}

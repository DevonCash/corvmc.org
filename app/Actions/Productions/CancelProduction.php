<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Notifications\ProductionCancelledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelProduction
{
    use AsAction;

    /**
     * Cancel a production.
     */
    public function handle(Production $production, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($production, $reason) {
            $production->update([
                'status' => 'cancelled',
                'description' => $production->description . ($reason ? "\n\nCancellation reason: {$reason}" : ''),
            ]);

            // Notify all stakeholders
            $users = GetInterestedUsers::run($production);
            if ($users->isNotEmpty()) {
                Notification::send($users, new ProductionCancelledNotification($production, $reason));
            }

            return true;
        });
    }
}

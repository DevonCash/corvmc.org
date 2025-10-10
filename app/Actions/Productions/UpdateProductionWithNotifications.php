<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Notifications\ProductionUpdatedNotification;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductionWithNotifications
{
    use AsAction;

    /**
     * Update production with change tracking for notifications.
     */
    public function handle(Production $production, array $attributes): bool
    {
        $originalValues = $production->only(array_keys($attributes));
        $production->update($attributes);

        // Track what changed
        $changes = [];
        foreach ($attributes as $key => $newValue) {
            if (isset($originalValues[$key]) && $originalValues[$key] !== $newValue) {
                $changes[$key] = [
                    'old' => $originalValues[$key],
                    'new' => $newValue,
                ];
            }
        }

        // Send notification if there were meaningful changes
        if (!empty($changes)) {
            $users = GetInterestedUsers::run($production);
            if ($users->isNotEmpty()) {
                Notification::send($users, new ProductionUpdatedNotification($production, 'updated', $changes));
            }
        }

        return true;
    }
}

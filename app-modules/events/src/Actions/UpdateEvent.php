<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Models\Event;
use CorvMC\Events\Notifications\EventUpdatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateEvent
{
    use AsAction;

    /**
     * Update an event.
     */
    public function handle(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            $originalData = $event->toArray();

            $event->update($data);
            // Update flags if provided
            if (isset($data['notaflof'])) {
                $event->setNotaflof($data['notaflof']);
            }

            // Update tags if provided
            if (isset($data['tags'])) {
                $event->syncTags($data['tags']);
            }

            // Send update notification if significant changes
            $this->sendUpdateNotificationIfNeeded($event, $originalData, $data);

            return $event->fresh();
        });
    }

    /**
     * Send update notification if significant changes occurred.
     */
    protected function sendUpdateNotificationIfNeeded(Event $event, array $originalData, array $newData): void
    {
        try {
            // Check for significant changes
            $significantFields = ['title', 'start_datetime', 'end_datetime', 'venue_id', 'status'];
            $hasSignificantChanges = false;

            // Check if any significant fields changed
            foreach ($significantFields as $field) {
                if (isset($newData[$field]) && ($originalData[$field] ?? null) !== $newData[$field]) {
                    $hasSignificantChanges = true;
                    break;
                }
            }

            if ($hasSignificantChanges) {
                $users = GetInterestedUsers::run($event);
                if ($users->isNotEmpty()) {
                    Notification::send($users, new EventUpdatedNotification($event, 'updated', $newData));
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send event update notifications', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            // Continue execution - notification failure shouldn't block the update
        }
    }
}

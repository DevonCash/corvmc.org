<?php

namespace CorvMC\Events\Actions;

use CorvMC\Events\Data\EventFormData;
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
        $formData = EventFormData::from($data);

        return DB::transaction(function () use ($event, $formData, $data) {
            $originalData = $event->toArray();

            // Get resolved model attributes from DTO
            $attributes = $formData->toModelAttributes();

            $event->update($attributes);

            // Update flags if provided
            $notaflof = $formData->getNotaflof();
            if ($notaflof !== null) {
                $event->setNotaflof($notaflof);
            }

            // Update tags if provided
            $tags = $formData->getTags();
            if ($tags !== null) {
                $event->syncTags($tags);
            }

            // Send update notification if significant changes
            $this->sendUpdateNotificationIfNeeded($event, $originalData, $attributes);

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

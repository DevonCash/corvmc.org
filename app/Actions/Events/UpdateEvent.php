<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Notifications\EventUpdatedNotification;
use Illuminate\Support\Facades\DB;
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

            // Convert location data if needed
            if (isset($data['at_cmc'])) {
                $data['location']['is_external'] = ! $data['at_cmc'];
                unset($data['at_cmc']);
            }

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
            // Check both actual fields and virtual fields for significant changes
            $significantFields = ['title', 'start_datetime', 'end_datetime', 'location', 'status'];
            $virtualFields = ['event_date', 'start_time', 'end_time'];
            $hasSignificantChanges = false;

            // Check actual database fields
            foreach ($significantFields as $field) {
                if (isset($newData[$field]) && ($originalData[$field] ?? null) !== $newData[$field]) {
                    $hasSignificantChanges = true;
                    break;
                }
            }

            // If no changes detected yet, check if virtual date/time fields were provided
            // (which indicates the date/time was changed via the form)
            if (!$hasSignificantChanges) {
                foreach ($virtualFields as $field) {
                    if (isset($newData[$field])) {
                        $hasSignificantChanges = true;
                        break;
                    }
                }
            }

            if ($hasSignificantChanges) {
                $users = GetInterestedUsers::run($event);
                if ($users->isNotEmpty()) {
                    Notification::send($users, new EventUpdatedNotification($event, 'updated', $newData));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send event update notifications', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            // Continue execution - notification failure shouldn't block the update
        }
    }
}

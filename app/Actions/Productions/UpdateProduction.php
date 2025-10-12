<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Notifications\ProductionUpdatedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProduction
{
    use AsAction;

    /**
     * Update a production.
     */
    public function handle(Production $production, array $data): Production
    {
        return DB::transaction(function () use ($production, $data) {
            $originalData = $production->toArray();

            // Convert location data if needed
            if (isset($data['at_cmc'])) {
                $data['location']['is_external'] = !$data['at_cmc'];
                unset($data['at_cmc']);
            }

            $production->update($data);

            // Update flags if provided
            if (isset($data['notaflof'])) {
                $production->setNotaflof($data['notaflof']);
            }

            // Update tags if provided
            if (isset($data['tags'])) {
                $production->syncTags($data['tags']);
            }

            // Send update notification if significant changes
            $this->sendUpdateNotificationIfNeeded($production, $originalData, $data);

            return $production->fresh();
        });
    }

    /**
     * Send update notification if significant changes occurred.
     */
    protected function sendUpdateNotificationIfNeeded(Production $production, array $originalData, array $newData): void
    {
        try {
            $significantFields = ['title', 'start_time', 'end_time', 'location', 'status'];
            $hasSignificantChanges = false;

            foreach ($significantFields as $field) {
                if (isset($newData[$field]) && ($originalData[$field] ?? null) !== $newData[$field]) {
                    $hasSignificantChanges = true;
                    break;
                }
            }

            if ($hasSignificantChanges) {
                $users = GetInterestedUsers::run($production);
                if ($users->isNotEmpty()) {
                    Notification::send($users, new ProductionUpdatedNotification($production, 'updated', $newData));
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send production update notifications', [
                'production_id' => $production->id,
                'error' => $e->getMessage(),
            ]);
            // Continue execution - notification failure shouldn't block the update
        }
    }
}

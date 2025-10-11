<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Notifications\ProductionCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateProduction
{
    use AsAction;

    /**
     * Create a new production.
     */
    public function handle(array $data): Production
    {
        return DB::transaction(function () use ($data) {
            // Convert location data if needed
            if (isset($data['at_cmc'])) {
                $data['location']['is_external'] = !$data['at_cmc'];
                unset($data['at_cmc']);
            }

            $data['status'] ??= 'pre-production';

            // Check for conflicts if this production uses the practice space
            if (isset($data['start_time']) && isset($data['end_time'])) {
                $isExternal = isset($data['location']['is_external']) ? $data['location']['is_external'] : false;
                if (!$isExternal) {
                    // Handle both Carbon instances and strings from form
                    $startTime = $data['start_time'] instanceof Carbon
                        ? $data['start_time']
                        : Carbon::parse($data['start_time'], config('app.timezone'));
                    $endTime = $data['end_time'] instanceof Carbon
                        ? $data['end_time']
                        : Carbon::parse($data['end_time'], config('app.timezone'));

                    $conflicts = \App\Actions\Reservations\GetAllConflicts::run($startTime, $endTime);

                    if ($conflicts['reservations']->isNotEmpty()) {
                        throw new \InvalidArgumentException('Production conflicts with existing reservation');
                    }
                }
            }

            $production = Production::create($data);

            // Set flags if provided
            if (isset($data['notaflof'])) {
                $production->setNotaflof($data['notaflof']);
            }

            // Attach tags if provided
            if (!empty($data['tags'])) {
                $production->attachTags($data['tags']);
            }

            // Notify manager
            if ($production->manager) {
                $production->manager->notify(new ProductionCreatedNotification($production));
            }

            return $production;
        });
    }
}

<?php

namespace App\Services;

use App\Models\Band;
use App\Models\Production;
use App\Models\User;
use App\Notifications\ProductionUpdatedNotification;
use App\Notifications\ProductionCancelledNotification;
use App\Notifications\ProductionCreatedNotification;
use App\Notifications\ProductionPublishedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ProductionService
{
    /**
     * Create a new production.
     */
    public function createProduction(array $data): Production
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
                    $conflicts = app(\App\Services\ReservationService::class)->getAllConflicts(
                        \Carbon\Carbon::parse($data['start_time']),
                        \Carbon\Carbon::parse($data['end_time'])
                    );

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

    /**
     * Update a production.
     */
    public function updateProduction(Production $production, array $data): Production
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
     * Delete a production.
     */
    public function deleteProduction(Production $production): bool
    {
        return DB::transaction(function () use ($production) {
            // Notify performers and manager
            $this->notifyProductionCancellation($production);

            return $production->delete();
        });
    }

    /**
     * Publish a production.
     */
    public function publishProduction(Production $production): void
    {
        // Check authorization
        if (auth()->check() && !Auth::user()->can('update', $production)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to publish this production.');
        }

        $production->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Notify performers and stakeholders
        $this->notifyProductionPublished($production);
    }

    /**
     * Cancel a production.
     */
    public function cancelProduction(Production $production, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($production, $reason) {
            $production->update([
                'status' => 'cancelled',
                'description' => $production->description . ($reason ? "\n\nCancellation reason: {$reason}" : ''),
            ]);

            // Notify all stakeholders
            $this->notifyProductionCancellation($production, $reason);

            return true;
        });
    }
    /**
     * Add a performer (band) to a production.
     */
    public function addPerformer(
        Production $production,
        Band $band,
        array $options = []
    ): bool {
        if ($this->hasPerformer($production, $band)) {
            return false;
        }

        // If no order specified, put them at the end
        if (!isset($options['order'])) {
            $options['order'] = $production->performers()->max('production_bands.order') + 1 ?? 1;
        }

        $production->performers()->attach($band->id, [
            'order' => $options['order'],
            'set_length' => $options['set_length'] ?? null,
        ]);

        return true;
    }

    /**
     * Remove a performer from a production.
     */
    public function removePerformer(Production $production, Band $band): bool
    {
        return $production->performers()->detach($band->id) > 0;
    }

    /**
     * Update a performer's order in the lineup.
     */
    public function updatePerformerOrder(Production $production, Band $band, int $order): bool
    {
        if (! $this->hasPerformer($production, $band)) {
            return false;
        }

        $production->performers()->updateExistingPivot($band->id, [
            'order' => $order,
        ]);

        return true;
    }

    /**
     * Update a performer's set length.
     */
    public function updatePerformerSetLength(Production $production, Band $band, ?int $setLength): bool
    {
        if (! $this->hasPerformer($production, $band)) {
            return false;
        }

        $production->performers()->updateExistingPivot($band->id, [
            'set_length' => $setLength,
        ]);

        return true;
    }

    /**
     * Reorder all performers in a production.
     */
    public function reorderPerformers(Production $production, array $bandIds): bool
    {
        DB::transaction(function () use ($production, $bandIds) {
            foreach ($bandIds as $index => $bandId) {
                $production->performers()->updateExistingPivot($bandId, [
                    'order' => $index + 1,
                ]);
            }
        });

        return true;
    }


    /**
     * Unpublish a production.
     */
    public function unpublishProduction(Production $production): bool
    {
        if (! $production->isPublished()) {
            return false;
        }

        $production->update([
            'status' => 'in-production',
            'published_at' => null,
        ]);

        return true;
    }

    /**
     * Mark a production as completed.
     */
    public function markAsCompleted(Production $production): bool
    {
        $production->update([
            'status' => 'completed',
        ]);

        return true;
    }


    /**
     * Transfer production management to another user.
     */
    public function transferManagement(Production $production, User $newManager): bool
    {
        $production->update([
            'manager_id' => $newManager->id,
        ]);

        return true;
    }

    /**
     * Get available bands for a production (bands that aren't already performing).
     */
    public function getAvailableBands(Production $production, string $search = ''): Collection
    {
        return Band::withTouringBands()
            ->where('name', 'like', "%{$search}%")
            ->whereNotIn('id', $production->performers->pluck('id'))
            ->limit(50)
            ->get();
    }

    /**
     * Get productions managed by a user.
     */
    public function getProductionsManagedBy(User $user): Collection
    {
        return Production::where('manager_id', $user->id)
            ->orderBy('start_time', 'desc')
            ->get();
    }

    /**
     * Get upcoming productions.
     */
    public function getUpcomingProductions(): Collection
    {
        return Production::where('status', 'published')
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Get published productions within a date range.
     */
    public function getProductionsInDateRange(\DateTime $startDate, \DateTime $endDate): Collection
    {
        return Production::where('status', 'published')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Get productions featuring a specific band.
     */
    public function getProductionsForBand(Band $band): Collection
    {
        return Production::whereHas('performers', function ($query) use ($band) {
            $query->where('band_profile_id', $band->id);
        })->orderBy('start_time', 'desc')->get();
    }

    /**
     * Check if a production has a specific performer.
     */
    public function hasPerformer(Production $production, Band $band): bool
    {
        return $production->performers()->where('band_profile_id', $band->id)->exists();
    }

    /**
     * Check if a user is the manager of a production.
     */
    public function isManager(Production $production, User $user): bool
    {
        return $production->manager_id === $user->id;
    }

    /**
     * Check if a user can manage a production (is manager or admin).
     */
    public function canManage(Production $production, User $user): bool
    {
        return $this->isManager($production, $user) || $user->can('manage productions');
    }

    /**
     * Get production statistics.
     */
    public function getProductionStats(): array
    {
        return [
            'total' => Production::count(),
            'published' => Production::where('status', 'published')->count(),
            'upcoming' => Production::where('status', 'published')
                ->where('start_time', '>', now())
                ->count(),
            'completed' => Production::where('status', 'completed')->count(),
            'in_production' => Production::where('status', 'in-production')->count(),
            'cancelled' => Production::where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Search productions by title, description, or venue.
     */
    public function searchProductions(string $query): Collection
    {
        return Production::where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhereJsonContains('location->venue_name', $query)
                ->orWhereJsonContains('location->city', $query);
        })
            ->where('status', 'published')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Get productions by genre.
     */
    public function getProductionsByGenre(string $genre): Collection
    {
        return Production::withAnyTags([$genre], 'genre')
            ->where('status', 'published')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Duplicate a production with new date/time.
     */
    public function duplicateProduction(
        Production $originalProduction,
        \DateTime $newStartTime,
        ?\DateTime $newEndTime = null,
        ?\DateTime $newDoorsTime = null
    ): Production {
        $newProduction = $originalProduction->replicate();
        $newProduction->start_time = $newStartTime;
        $newProduction->end_time = $newEndTime;
        $newProduction->doors_time = $newDoorsTime;
        $newProduction->status = 'pre-production';
        $newProduction->published_at = null;
        $newProduction->save();

        // Copy performers
        foreach ($originalProduction->performers as $performer) {
            $newProduction->performers()->attach($performer->id, [
                'order' => $performer->pivot->order,
                'set_length' => $performer->pivot->set_length,
            ]);
        }

        // Copy tags
        foreach ($originalProduction->tags as $tag) {
            $newProduction->attachTag($tag->name, $tag->type);
        }

        return $newProduction;
    }

    /**
     * Notify interested users about production updates.
     */
    private function notifyInterestedUsers(Production $production, string $updateType, array $changes = []): void
    {
        // Get all users who should be notified
        $users = $this->getInterestedUsers($production);

        if ($users->isNotEmpty()) {
            Notification::send($users, new ProductionUpdatedNotification($production, $updateType, $changes));
        }
    }

    /**
     * Get users who should be notified about production updates.
     * This includes: manager, band members, and optionally all sustaining members for published events.
     */
    private function getInterestedUsers(Production $production): Collection
    {
        $users = User::query()->whereNull('id')->get(); // Start with empty EloquentCollection

        // Always notify the production manager
        if ($production->manager) {
            $users = $users->merge([$production->manager]);
        }

        // Notify all band members performing in this production
        foreach ($production->performers as $band) {
            $users = $band->members()->get();
        }

        // For published events, optionally notify all sustaining members
        // (This could be made configurable via settings)
        if ($production->isPublished()) {
            $sustainingMembers = User::role('sustaining member')->get();
            $users = $users->merge($sustainingMembers);
        }

        // Remove duplicates and filter out null values
        return $users->filter()->unique('id');
    }

    /**
     * Update production with change tracking for notifications.
     */
    public function updateProductionWithNotifications(Production $production, array $attributes): bool
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
            $this->notifyInterestedUsers($production, 'updated', $changes);
        }

        return true;
    }

    /**
     * Send update notification if significant changes occurred.
     */
    private function sendUpdateNotificationIfNeeded(Production $production, array $originalData, array $newData): void
    {
        $significantFields = ['title', 'start_time', 'end_time', 'location', 'status'];
        $hasSignificantChanges = false;

        foreach ($significantFields as $field) {
            if (isset($newData[$field]) && $originalData[$field] !== $newData[$field]) {
                $hasSignificantChanges = true;
                break;
            }
        }

        if ($hasSignificantChanges) {
            $this->notifyInterestedUsers($production, 'updated', $newData);
        }
    }

    /**
     * Notify about production publication.
     */
    private function notifyProductionPublished(Production $production): void
    {
        $users = $this->getInterestedUsers($production);
        if ($users->isNotEmpty()) {
            Notification::send($users, new ProductionPublishedNotification($production));
        }
    }

    /**
     * Notify about production cancellation.
     */
    private function notifyProductionCancellation(Production $production, ?string $reason = null): void
    {
        $users = $this->getInterestedUsers($production);
        if ($users->isNotEmpty()) {
            Notification::send($users, new ProductionCancelledNotification($production, $reason));
        }
    }
}

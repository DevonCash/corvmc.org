<?php

namespace App\Services;

use App\Models\BandProfile;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    /**
     * Add a performer (band) to a production.
     */
    public function addPerformer(
        Production $production,
        BandProfile $band,
        int $order = 0,
        ?int $setLength = null
    ): bool {
        if ($this->hasPerformer($production, $band)) {
            return false;
        }

        // If no order specified, put them at the end
        if ($order === 0) {
            $order = $production->performers()->max('production_bands.order') + 1 ?? 1;
        }

        $production->performers()->attach($band->id, [
            'order' => $order,
            'set_length' => $setLength,
        ]);

        return true;
    }

    /**
     * Remove a performer from a production.
     */
    public function removePerformer(Production $production, BandProfile $band): bool
    {
        return $production->performers()->detach($band->id) > 0;
    }

    /**
     * Update a performer's order in the lineup.
     */
    public function updatePerformerOrder(Production $production, BandProfile $band, int $order): bool
    {
        if (!$this->hasPerformer($production, $band)) {
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
    public function updatePerformerSetLength(Production $production, BandProfile $band, ?int $setLength): bool
    {
        if (!$this->hasPerformer($production, $band)) {
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
     * Publish a production.
     */
    public function publishProduction(Production $production): bool
    {
        if ($production->isPublished()) {
            return false;
        }

        $production->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        return true;
    }

    /**
     * Unpublish a production.
     */
    public function unpublishProduction(Production $production): bool
    {
        if (!$production->isPublished()) {
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
     * Cancel a production.
     */
    public function cancelProduction(Production $production): bool
    {
        $production->update([
            'status' => 'cancelled',
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
        return BandProfile::withTouringBands()
            ->where('name', 'like', "%{$search}%")
            ->whereDoesntHave('productions', fn ($query) => 
                $query->where('production_id', $production->id)
            )
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
    public function getProductionsForBand(BandProfile $band): Collection
    {
        return Production::whereHas('performers', fn ($query) => 
            $query->where('band_profile_id', $band->id)
        )
        ->orderBy('start_time', 'desc')
        ->get();
    }

    /**
     * Check if a production has a specific performer.
     */
    public function hasPerformer(Production $production, BandProfile $band): bool
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
        return $this->isManager($production, $user) || $user->hasRole('admin');
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
}
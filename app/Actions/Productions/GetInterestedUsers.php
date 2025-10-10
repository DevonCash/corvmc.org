<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetInterestedUsers
{
    use AsAction;

    /**
     * Get users who should be notified about production updates.
     * This includes: manager, band members, and optionally all sustaining members for published events.
     */
    public function handle(Production $production): Collection
    {
        $users = User::query()->whereNull('id')->get(); // Start with empty EloquentCollection

        // Always notify the production manager
        if ($production->manager) {
            $users = $users->merge([$production->manager]);
        }

        // Notify all band members performing in this production
        foreach ($production->performers as $band) {
            $bandMembers = $band->members()->get();
            $users = $users->merge($bandMembers);
        }

        // For published events, optionally notify all sustaining members
        if ($production->isPublished()) {
            $sustainingMembers = User::role('sustaining member')->get();
            $users = $users->merge($sustainingMembers);
        }

        // Remove duplicates and filter out null values
        return $users->filter()->unique('id');
    }
}

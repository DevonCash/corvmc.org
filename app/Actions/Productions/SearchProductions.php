<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchProductions
{
    use AsAction;

    /**
     * Search productions by title, description, or venue.
     */
    public function handle(string $query): Collection
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
}

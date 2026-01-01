<?php

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchEvents
{
    use AsAction;

    /**
     * Search events by title, description, or performers.
     */
    public function handle(string $query): Collection
    {
        return Event::query()
            ->where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orWhereHas('performers', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orderBy('start_datetime', 'desc')
            ->limit(50)
            ->get();
    }
}

<?php

namespace App\Actions\Productions;

use App\Models\Production;
use App\Notifications\ProductionPublishedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishProduction
{
    use AsAction;

    /**
     * Publish a production.
     */
    public function handle(Production $production): void
    {
        // Check authorization
        if (!Auth::user()?->can('update', $production)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to publish this production.');
        }

        $production->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Notify performers and stakeholders
        $users = GetInterestedUsers::run($production);
        if ($users->isNotEmpty()) {
            Notification::send($users, new ProductionPublishedNotification($production));
        }
    }
}

<?php

namespace App\Listeners;

use CorvMC\Membership\Events\UserCreated;
use CorvMC\Membership\Events\UserDeleted;
use CorvMC\Membership\Events\UserUpdated;

class LogUserActivity
{
    public function handleCreated(UserCreated $event): void
    {
        $user = $event->user;

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user() ?? $user)
            ->event('created')
            ->log("User account created: {$user->name}");
    }

    public function handleUpdated(UserUpdated $event): void
    {
        $user = $event->user;
        $summary = implode(', ', $event->changedFields);

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'changed_fields' => $event->changedFields,
                'old_values' => $event->oldValues,
            ])
            ->log("User account updated: {$summary}");
    }

    public function handleDeleted(UserDeleted $event): void
    {
        $user = $event->user;

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->event('deleted')
            ->log("User account deleted: {$user->name}");
    }
}

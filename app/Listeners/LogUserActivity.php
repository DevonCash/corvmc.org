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

    /**
     * Fields that trigger activity logging when changed.
     */
    private const TRACKED_FIELDS = ['name', 'email', 'phone'];

    public function handleUpdated(UserUpdated $event): void
    {
        $user = $event->user;
        $trackedChanges = array_intersect($event->changedFields, self::TRACKED_FIELDS);

        if (empty($trackedChanges)) {
            return;
        }

        $summary = implode(', ', $trackedChanges);
        $oldValues = array_intersect_key($event->oldValues, array_flip($trackedChanges));

        activity('user')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'changed_fields' => array_values($trackedChanges),
                'old_values' => $oldValues,
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

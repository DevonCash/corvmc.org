<?php

namespace App\Listeners;

use CorvMC\Membership\Events\MemberProfileCreated;
use CorvMC\Membership\Events\MemberProfileDeleted;
use CorvMC\Membership\Events\MemberProfileUpdated;

class LogMemberProfileActivity
{
    public function handleCreated(MemberProfileCreated $event): void
    {
        $profile = $event->profile;

        activity('member_profile')
            ->performedOn($profile)
            ->causedBy(auth()->user())
            ->event('created')
            ->log("Member profile created");
    }

    public function handleUpdated(MemberProfileUpdated $event): void
    {
        $profile = $event->profile;
        $summary = implode(', ', $event->changedFields);

        activity('member_profile')
            ->performedOn($profile)
            ->causedBy(auth()->user())
            ->event('updated')
            ->withProperties([
                'changed_fields' => $event->changedFields,
                'old_values' => $event->oldValues,
            ])
            ->log("Member profile updated: {$summary}");
    }

    public function handleDeleted(MemberProfileDeleted $event): void
    {
        $profile = $event->profile;

        activity('member_profile')
            ->performedOn($profile)
            ->causedBy(auth()->user())
            ->event('deleted')
            ->log("Member profile deleted");
    }
}

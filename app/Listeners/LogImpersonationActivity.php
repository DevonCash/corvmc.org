<?php

namespace App\Listeners;

use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

class LogImpersonationActivity
{
    public function handleTakeImpersonation(TakeImpersonation $event): void
    {
        activity('impersonation')
            ->causedBy($event->impersonator)
            ->performedOn($event->impersonated)
            ->event('started')
            ->withProperties(['action' => 'start'])
            ->log("Started impersonating {$event->impersonated->name}");
    }

    public function handleLeaveImpersonation(LeaveImpersonation $event): void
    {
        activity('impersonation')
            ->causedBy($event->impersonator)
            ->performedOn($event->impersonated)
            ->event('ended')
            ->withProperties(['action' => 'stop'])
            ->log("Stopped impersonating {$event->impersonated->name}");
    }
}

<?php

namespace CorvMC\Volunteering\Events;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a volunteer checks in to a shift (Confirmed → CheckedIn).
 */
class VolunteerCheckedIn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HourLog $hourLog,
    ) {}
}

<?php

namespace CorvMC\Volunteering\Events;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a volunteer is released from a shift.
 */
class VolunteerReleased
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HourLog $hourLog,
    ) {}
}

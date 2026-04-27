<?php

namespace CorvMC\Volunteering\Events;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when self-reported hours are approved by staff (Pending → Approved).
 */
class HoursApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HourLog $hourLog,
    ) {}
}

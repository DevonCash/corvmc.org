<?php

namespace CorvMC\Volunteering\Events;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a volunteer submits self-reported hours for review.
 */
class HoursSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HourLog $hourLog,
    ) {}
}

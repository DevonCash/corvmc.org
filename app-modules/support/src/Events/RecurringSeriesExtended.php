<?php

namespace CorvMC\Support\Events;

use Carbon\Carbon;
use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringSeriesExtended
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RecurringSeries $series,
        public Carbon $previousEndDate,
    ) {}
}

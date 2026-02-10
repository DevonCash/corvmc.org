<?php

namespace CorvMC\Support\Events;

use CorvMC\Support\Models\RecurringSeries;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecurringSeriesCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RecurringSeries $series,
    ) {}
}

<?php

namespace CorvMC\Bands\Events;

use CorvMC\Bands\Models\Band;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BandCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Band $band,
    ) {}
}

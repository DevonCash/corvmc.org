<?php

namespace CorvMC\Moderation\Events;

use CorvMC\Moderation\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Report $report,
    ) {}
}

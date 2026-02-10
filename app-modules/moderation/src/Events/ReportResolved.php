<?php

namespace CorvMC\Moderation\Events;

use App\Models\User;
use CorvMC\Moderation\Models\Report;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportResolved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Report $report,
        public User $moderator,
        public string $status,
    ) {}
}

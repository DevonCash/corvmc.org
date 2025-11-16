<?php

namespace App\Actions\ActivityLogs;

use App\Concerns\AsFilamentAction;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Activitylog\Models\Activity;

class CleanupLogs
{
    use AsAction;
    use AsFilamentAction;

    public static $actionRequiresConfirmation = true;

    public function handle()
    {
        Activity::where('created_at', '<', now()->subDays(90))->delete();
    }
}

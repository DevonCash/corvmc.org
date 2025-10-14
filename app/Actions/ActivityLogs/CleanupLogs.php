<?php

namespace App\Actions\ActivityLogs;

use Spatie\Activitylog\Models\Activity;

use App\Concerns\AsFilamentAction;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanupLogs {
    use AsAction;
    use AsFilamentAction;

    static $actionRequiresConfirmation = true;

    public function handle() {
        Activity::where('created_at', '<', now()->subDays(90))->delete();
    }
}

<?php

namespace App\Actions\Reports;

use App\Models\Report;
use Lorisleiva\Actions\Concerns\AsAction;

class GetReportsNeedingAttention
{
    use AsAction;

    /**
     * Get reports requiring moderation attention.
     */
    public function handle(): \Illuminate\Database\Eloquent\Collection
    {
        return Report::with(['reportable', 'reportedBy'])
            ->where('status', 'pending')
            ->orWhere('status', 'escalated')
            ->orderBy('created_at', 'asc')
            ->get();
    }
}

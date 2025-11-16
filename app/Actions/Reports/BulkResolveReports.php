<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class BulkResolveReports
{
    use AsAction;

    /**
     * Bulk resolve multiple reports.
     */
    public function handle(
        array $reportIds,
        User $moderator,
        string $status,
        ?string $notes = null
    ): int {
        $resolvedCount = 0;

        $reports = Report::whereIn('id', $reportIds)
            ->where('status', 'pending')
            ->get();

        foreach ($reports as $report) {
            try {
                ResolveReport::run($report, $moderator, $status, $notes);
                $resolvedCount++;
            } catch (\Exception $e) {
                // Log error but continue with other reports
                logger()->error("Failed to resolve report {$report->id}: ".$e->getMessage());
            }
        }

        return $resolvedCount;
    }
}

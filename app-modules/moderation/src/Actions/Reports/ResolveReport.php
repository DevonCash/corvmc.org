<?php

namespace CorvMC\Moderation\Actions\Reports;

use CorvMC\Moderation\Events\ReportResolved as ReportResolvedEvent;
use CorvMC\Moderation\Models\Report;
use CorvMC\Moderation\Notifications\ReportResolvedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveReport
{
    use AsAction;

    /**
     * Resolve a report (uphold, dismiss, or escalate).
     */
    public function handle(
        Report $report,
        User $moderator,
        string $status,
        ?string $notes = null
    ): Report {
        if (! in_array($status, ['upheld', 'dismissed', 'escalated'])) {
            throw new \Exception('Invalid resolution status');
        }

        $report->update([
            'status' => $status,
            'resolved_by_id' => $moderator->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        ReportResolvedEvent::dispatch($report, $moderator, $status);

        // Notify the reporter about the resolution
        if (in_array($status, ['upheld', 'dismissed'])) {
            try {
                $report->reportedBy->notify(new ReportResolvedNotification($report));
            } catch (\Exception $e) {
                Log::error('Failed to send report resolved notification', [
                    'report_id' => $report->id,
                    'reporter_id' => $report->reportedBy->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $report->fresh();
    }

}

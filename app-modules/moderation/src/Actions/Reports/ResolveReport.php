<?php

namespace CorvMC\Moderation\Actions\Reports;

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

        // Handle the resolution outcome
        match ($status) {
            'upheld' => $this->handleUpheldReport($report),
            'dismissed' => $this->handleDismissedReport($report),
            'escalated' => $this->handleEscalatedReport($report),
        };

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

    /**
     * Handle upheld report.
     */
    private function handleUpheldReport(Report $report): void
    {
        // Log the upheld report for potential user trust level impacts
        activity()
            ->performedOn($report->reportable)
            ->causedBy($report->resolvedBy)
            ->withProperties([
                'report_id' => $report->id,
                'reason' => $report->reason,
                'reported_by' => $report->reportedBy->name,
            ])
            ->log('Report upheld by moderator');

        // Could implement penalties for reported users here
        // For now, just log the activity
    }

    /**
     * Handle dismissed report.
     */
    private function handleDismissedReport(Report $report): void
    {
        // Log the dismissal
        activity()
            ->performedOn($report->reportable)
            ->causedBy($report->resolvedBy)
            ->withProperties([
                'report_id' => $report->id,
                'reason' => $report->reason,
                'reported_by' => $report->reportedBy->name,
            ])
            ->log('Report dismissed by moderator');

        // Could implement penalties for false reporting here
    }

    /**
     * Handle escalated report.
     */
    private function handleEscalatedReport(Report $report): void
    {
        // Log the escalation
        activity()
            ->performedOn($report->reportable)
            ->causedBy($report->resolvedBy)
            ->withProperties([
                'report_id' => $report->id,
                'reason' => $report->reason,
                'escalated_by' => $report->resolvedBy->name,
            ])
            ->log('Report escalated for admin review');

        // Notify admins about escalated report
        logger()->info("Notifying admins about escalated report: {$report->id}");
    }
}

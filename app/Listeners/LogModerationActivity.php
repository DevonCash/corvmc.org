<?php

namespace App\Listeners;

use CorvMC\Moderation\Events\ReportResolved;
use CorvMC\Moderation\Events\ReportSubmitted;
use CorvMC\Moderation\Events\RevisionApproved;
use CorvMC\Moderation\Events\RevisionAutoApproved;
use CorvMC\Moderation\Events\RevisionRejected;
use CorvMC\Moderation\Events\RevisionSubmitted;

class LogModerationActivity
{
    public function handleReportSubmitted(ReportSubmitted $event): void
    {
        $report = $event->report;

        activity('moderation')
            ->performedOn($report)
            ->causedBy($report->reportedBy)
            ->event('report_submitted')
            ->withProperties([
                'reason' => $report->reason,
                'reportable_type' => $report->reportable_type,
                'reportable_id' => $report->reportable_id,
            ])
            ->log("Report submitted: {$report->reason_label} on {$report->reportable_type}");
    }

    public function handleReportResolved(ReportResolved $event): void
    {
        $report = $event->report;
        $status = $event->status;
        $notes = $report->resolution_notes ?? '';

        // Log on the report itself
        $description = match ($status) {
            'upheld' => "Report upheld" . ($notes ? ": {$notes}" : ''),
            'dismissed' => "Report dismissed" . ($notes ? ": {$notes}" : ''),
            'escalated' => "Report escalated" . ($notes ? ": {$notes}" : ''),
            default => "Report resolved: {$status}",
        };

        activity('moderation')
            ->performedOn($report)
            ->causedBy($event->moderator)
            ->event("report_{$status}")
            ->withProperties([
                'status' => $status,
                'resolution_notes' => $notes,
            ])
            ->log($description);

        // Also log on the reportable (replicating existing inline activity() from ResolveReport)
        if ($report->reportable) {
            $reportableDescription = match ($status) {
                'upheld' => 'Report upheld by moderator',
                'dismissed' => 'Report dismissed by moderator',
                'escalated' => 'Report escalated for admin review',
                default => "Report {$status}",
            };

            activity('moderation')
                ->performedOn($report->reportable)
                ->causedBy($event->moderator)
                ->event("report_{$status}")
                ->withProperties([
                    'report_id' => $report->id,
                    'reason' => $report->reason,
                    'reported_by' => $report->reportedBy->name,
                ])
                ->log($reportableDescription);
        }
    }

    public function handleRevisionSubmitted(RevisionSubmitted $event): void
    {
        $revision = $event->revision;

        activity('moderation')
            ->performedOn($revision)
            ->causedBy($revision->submittedBy)
            ->event('revision_submitted')
            ->withProperties([
                'revisionable_type' => $revision->revisionable_type,
                'changes_count' => count($revision->proposed_changes),
            ])
            ->log("Revision submitted for {$revision->getModelTypeName()}: {$revision->getChangesSummary()}");
    }

    public function handleRevisionApproved(RevisionApproved $event): void
    {
        $revision = $event->revision;

        activity('moderation')
            ->performedOn($revision)
            ->causedBy($event->reviewer)
            ->event('revision_approved')
            ->log('Revision approved');
    }

    public function handleRevisionRejected(RevisionRejected $event): void
    {
        $revision = $event->revision;

        activity('moderation')
            ->performedOn($revision)
            ->causedBy($event->reviewer)
            ->event('revision_rejected')
            ->withProperties([
                'reason' => $event->reason,
            ])
            ->log("Revision rejected: {$event->reason}");
    }

    public function handleRevisionAutoApproved(RevisionAutoApproved $event): void
    {
        $revision = $event->revision;

        activity('moderation')
            ->performedOn($revision)
            ->event('revision_auto_approved')
            ->log('Revision auto-approved');
    }
}

<?php

namespace CorvMC\Moderation\Services;

use App\Models\User;
use CorvMC\Moderation\Enums\ReportStatus;
use CorvMC\Moderation\Events\ReportResolved;
use CorvMC\Moderation\Events\ReportSubmitted;
use CorvMC\Moderation\Models\Report;
use CorvMC\Moderation\Notifications\ReportResolvedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing user reports and moderation actions.
 * 
 * This service handles report submission, resolution, and tracking
 * of problematic content or user behavior.
 */
class ReportService
{
    /**
     * Submit a new report.
     *
     * @param User $reporter The user submitting the report
     * @param string $reportableType Type of entity being reported
     * @param int $reportableId ID of entity being reported
     * @param string $reason Reason for the report
     * @param string|null $details Optional additional details
     * @return Report The created report
     */
    public function submitReport(
        User $reporter,
        string $reportableType,
        int $reportableId,
        string $reason,
        ?string $details = null
    ): Report {
        // Check for duplicate reports
        $existing = Report::where('reported_by_id', $reporter->id)
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $reportableId)
            ->where('status', ReportStatus::Pending)
            ->first();

        if ($existing) {
            throw new \Exception('You have already reported this content');
        }

        $report = Report::create([
            'reported_by_id' => $reporter->id,
            'reportable_type' => $reportableType,
            'reportable_id' => $reportableId,
            'reason' => $reason,
            'custom_reason' => $details,
            'status' => ReportStatus::Pending,
        ]);

        ReportSubmitted::dispatch($report);

        return $report;
    }

    /**
     * Resolve a report.
     *
     * @param Report $report The report to resolve
     * @param User $resolver The user resolving the report
     * @param string $action Action taken (e.g., 'removed', 'warned', 'dismissed')
     * @param string|null $notes Optional resolution notes
     * @return Report The resolved report
     */
    public function resolveReport(
        Report $report,
        User $resolver,
        string $action,
        ?string $notes = null
    ): Report {
        return DB::transaction(function () use ($report, $resolver, $action, $notes) {
            $status = $action === 'dismissed' ? ReportStatus::Dismissed : ReportStatus::Upheld;

            $report->update([
                'status' => $status,
                'resolved_by_id' => $resolver->id,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
            ]);

            // Handle specific actions
            $this->handleResolutionAction($report, $action);

            // Notify reporter if configured
            if ($report->reportedBy && $this->shouldNotifyReporter($action)) {
                $report->reportedBy->notify(new ReportResolvedNotification($report));
            }

            ReportResolved::dispatch($report, $resolver, $action);

            return $report;
        });
    }

    /**
     * Bulk resolve multiple reports.
     *
     * @param array $reportIds IDs of reports to resolve
     * @param User $resolver User resolving the reports
     * @param string $action Action to take
     * @param string|null $notes Optional notes
     * @return int Number of reports resolved
     */
    public function bulkResolveReports(
        array $reportIds,
        User $resolver,
        string $action,
        ?string $notes = null
    ): int {
        $reports = Report::whereIn('id', $reportIds)
            ->where('status', ReportStatus::Pending)
            ->get();

        $resolved = 0;
        foreach ($reports as $report) {
            $this->resolveReport($report, $resolver, $action, $notes);
            $resolved++;
        }

        return $resolved;
    }

    /**
     * Get reports needing attention.
     *
     * @param array $filters Optional filters (priority, age, type)
     * @return Collection Reports requiring attention
     */
    public function getReportsNeedingAttention(array $filters = []): Collection
    {
        $query = Report::where('status', ReportStatus::Pending);

        // Apply filters
        if (isset($filters['priority'])) {
            // Reports with multiple reporters get priority
            $query->withCount('duplicates')
                ->orderByDesc('duplicates_count');
        }

        if (isset($filters['age'])) {
            $query->where('created_at', '<=', now()->subHours($filters['age']));
        }

        if (isset($filters['type'])) {
            $query->where('reportable_type', $filters['type']);
        }

        if (isset($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        // Default ordering
        if (!isset($filters['priority'])) {
            $query->oldest();
        }

        return $query->get();
    }

    /**
     * Mark a report as reviewed without resolving.
     *
     * @param Report $report The report to mark
     * @param User $reviewer The reviewing user
     * @param string $notes Review notes
     * @return Report The updated report
     */
    public function markAsReviewed(Report $report, User $reviewer, string $notes): Report
    {
        $report->update([
            'status' => ReportStatus::Pending,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        return $report;
    }

    /**
     * Escalate a report to higher authority.
     *
     * @param Report $report The report to escalate
     * @param User $escalatedBy User escalating the report
     * @param string $reason Reason for escalation
     * @return Report The escalated report
     */
    public function escalateReport(Report $report, User $escalatedBy, string $reason): Report
    {
        $report->update([
            'status' => ReportStatus::Escalated,
            'escalated_by' => $escalatedBy->id,
            'escalated_at' => now(),
            'escalation_reason' => $reason,
        ]);

        // Notify admins
        $this->notifyAdminsOfEscalation($report);

        return $report;
    }

    /**
     * Handle specific resolution actions.
     *
     * @param Report $report The report being resolved
     * @param string $action The action taken
     */
    protected function handleResolutionAction(Report $report, string $action): void
    {
        switch ($action) {
            case 'removed':
                // Soft delete the reported content if applicable
                if ($reportable = $report->reportable) {
                    if (method_exists($reportable, 'delete')) {
                        $reportable->delete();
                    }
                }
                break;
                
            case 'warned':
                // Issue warning to content owner
                // This would integrate with a warning system
                break;
                
            case 'banned':
                // Ban the user if applicable
                // This would integrate with user management
                break;
        }
    }

    /**
     * Determine if reporter should be notified.
     *
     * @param string $action Resolution action
     * @return bool
     */
    protected function shouldNotifyReporter(string $action): bool
    {
        // Don't notify for dismissed reports to avoid arguments
        return !in_array($action, ['dismissed', 'invalid']);
    }

    /**
     * Notify admins of an escalated report.
     *
     * @param Report $report The escalated report
     */
    protected function notifyAdminsOfEscalation(Report $report): void
    {
        $admins = User::role('admin')->get();
        
        foreach ($admins as $admin) {
            // Send escalation notification
            // Implementation would go here
        }
    }
}
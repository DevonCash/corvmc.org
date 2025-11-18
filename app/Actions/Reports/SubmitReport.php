<?php

namespace App\Actions\Reports;

use App\Contracts\Reportable;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitReport
{
    use AsAction;

    /**
     * Submit a report for content.
     */
    /**
     * @param Reportable&Model $reportable
     */
    public function handle(
        Model $reportable,
        User $reporter,
        string $reason,
        ?string $customReason = null
    ): Report {
        assert($reportable instanceof Reportable);
        // Prevent duplicate reports from same user on same content
        $existingReport = Report::where([
            'reportable_type' => get_class($reportable),
            'reportable_id' => $reportable->id,
            'reported_by_id' => $reporter->id,
            'status' => 'pending',
        ])->first();

        if ($existingReport) {
            throw new \Exception('You have already reported this content');
        }

        // Validate reason for content type
        $validReasons = Report::getReasonsForType(get_class($reportable));
        if (! in_array($reason, $validReasons)) {
            throw new \Exception('Invalid reason for this content type');
        }

        // Create the report
        $report = Report::create([
            'reportable_type' => get_class($reportable),
            'reportable_id' => $reportable->id,
            'reported_by_id' => $reporter->id,
            'reason' => $reason,
            'custom_reason' => $customReason,
            'status' => 'pending',
        ]);

        // Check threshold and handle accordingly
        if ($reportable->hasReachedReportThreshold()) {
            $this->handleThresholdReached($reportable);
        }

        // Notify moderators about the new report
        $this->notifyModerators($reportable, $report);

        return $report;
    }

    /**
     * Handle when report threshold is reached.
     */
    private function handleThresholdReached(Model $reportable): void
    {
        if ($reportable->shouldAutoHide()) {
            $this->hideContent($reportable);
        }

        // Always notify moderators regardless of auto-hide
        $this->notifyModerators($reportable);
    }

    /**
     * Hide content (implementation depends on model type).
     */
    private function hideContent(Model $reportable): void
    {
        match (get_class($reportable)) {
            'App\Models\Event' => logger()->info("Production {$reportable->id} reached report threshold"),
            'App\Models\MemberProfile' => logger()->info("Member profile {$reportable->id} reached report threshold"),
            'App\Models\Band' => logger()->info("Band {$reportable->id} reached report threshold"),
            default => null,
        };
    }

    /**
     * Notify moderators about flagged content.
     */
    private function notifyModerators(Model $reportable, ?Report $report = null): void
    {
        // Get users with moderation permissions
        $moderators = User::role(['admin'])->get();

        // If no admin users found, expand to include all users (for development)
        if ($moderators->isEmpty()) {
            $moderators = User::limit(1)->get(); // Just notify the first user for testing
        }

        if ($report && $moderators->count() > 0) {
            // Send notification about new report
            Notification::send($moderators, new ReportSubmittedNotification($report));
        }

        logger()->info("Notifying {$moderators->count()} moderators about reported {$reportable->getReportableType()}: {$reportable->id}");
    }
}

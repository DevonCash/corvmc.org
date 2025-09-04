<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\ReportResolvedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Exception;

class ReportService
{
    /**
     * Submit a report for content
     */
    public function submitReport(
        Model $reportable, 
        User $reporter, 
        string $reason, 
        ?string $customReason = null
    ): Report {
        // Prevent duplicate reports from same user on same content
        $existingReport = Report::where([
            'reportable_type' => get_class($reportable),
            'reportable_id' => $reportable->id,
            'reported_by_id' => $reporter->id,
            'status' => 'pending'
        ])->first();
        
        if ($existingReport) {
            throw new Exception('You have already reported this content');
        }
        
        // Validate reason for content type
        $validReasons = Report::getReasonsForType(get_class($reportable));
        if (!in_array($reason, $validReasons)) {
            throw new Exception('Invalid reason for this content type');
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
     * Resolve a report (uphold, dismiss, or escalate)
     */
    public function resolveReport(
        Report $report, 
        User $moderator, 
        string $status, 
        ?string $notes = null
    ): Report {
        if (!in_array($status, ['upheld', 'dismissed', 'escalated'])) {
            throw new Exception('Invalid resolution status');
        }
        
        $report->update([
            'status' => $status,
            'resolved_by_id' => $moderator->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
        
        // Handle the resolution outcome
        match($status) {
            'upheld' => $this->handleUpheldReport($report),
            'dismissed' => $this->handleDismissedReport($report),
            'escalated' => $this->handleEscalatedReport($report),
        };
        
        // Notify the reporter about the resolution
        if (in_array($status, ['upheld', 'dismissed'])) {
            $report->reportedBy->notify(new ReportResolvedNotification($report));
        }
        
        return $report->fresh();
    }
    
    /**
     * Bulk resolve multiple reports
     */
    public function bulkResolveReports(
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
                $this->resolveReport($report, $moderator, $status, $notes);
                $resolvedCount++;
            } catch (Exception $e) {
                // Log error but continue with other reports
                logger()->error("Failed to resolve report {$report->id}: " . $e->getMessage());
            }
        }
        
        return $resolvedCount;
    }
    
    /**
     * Get reports requiring moderation attention
     */
    public function getReportsNeedingAttention(): \Illuminate\Database\Eloquent\Collection
    {
        return Report::with(['reportable', 'reportedBy'])
            ->where('status', 'pending')
            ->orWhere('status', 'escalated')
            ->orderBy('created_at', 'asc')
            ->get();
    }
    
    /**
     * Handle when report threshold is reached
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
     * Hide content (implementation depends on model type)
     */
    private function hideContent(Model $reportable): void
    {
        match(get_class($reportable)) {
            'App\Models\Production' => $this->hideProduction($reportable),
            'App\Models\MemberProfile' => $this->hideMemberProfile($reportable),
            'App\Models\Band' => $this->hideBand($reportable),
            default => null,
        };
    }
    
    private function hideProduction($production): void
    {
        // Productions could be marked as cancelled or hidden
        // For now, just flag for manual review since auto-hide is disabled
        logger()->info("Production {$production->id} reached report threshold");
    }
    
    private function hideMemberProfile($profile): void
    {
        // Member profiles should not be auto-hidden
        logger()->info("Member profile {$profile->id} reached report threshold");
    }
    
    private function hideBand($band): void
    {
        // Bands should not be auto-hidden
        logger()->info("Band {$band->id} reached report threshold");
    }
    
    /**
     * Handle upheld report
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
     * Handle dismissed report
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
     * Handle escalated report
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
        $this->notifyAdmins($report);
    }
    
    /**
     * Notify moderators about flagged content
     */
    private function notifyModerators(Model $reportable, ?Report $report = null): void
    {
        // Get users with moderation permissions (try admin first, fallback to all users with any role)
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
    
    /**
     * Notify admins about escalated reports
     */
    private function notifyAdmins(Report $report): void
    {
        // Implementation would send notifications to admin users
        logger()->info("Notifying admins about escalated report: {$report->id}");
    }
}
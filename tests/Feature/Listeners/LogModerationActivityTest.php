<?php

use App\Models\User;
use CorvMC\Moderation\Events\ReportResolved;
use CorvMC\Moderation\Events\ReportSubmitted;
use CorvMC\Moderation\Events\RevisionApproved;
use CorvMC\Moderation\Events\RevisionAutoApproved;
use CorvMC\Moderation\Events\RevisionRejected;
use CorvMC\Moderation\Events\RevisionSubmitted;
use CorvMC\Moderation\Models\Report;
use CorvMC\Moderation\Models\Revision;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    Activity::query()->delete();
});

describe('Report events', function () {
    it('logs activity when a report is submitted', function () {
        $report = Report::factory()->pending()->create(['reason' => 'spam']);

        Activity::query()->delete();

        ReportSubmitted::dispatch($report);

        $activity = Activity::where('event', 'report_submitted')
            ->where('log_name', 'moderation')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toContain('Report submitted:')
            ->and($activity->description)->toContain('Spam or Duplicate')
            ->and($activity->subject_id)->toBe($report->id)
            ->and($activity->properties)->toHaveKey('reason');
    });

    it('logs activity when a report is upheld', function () {
        $moderator = User::factory()->create();
        $report = Report::factory()->pending()->create();
        $report->update([
            'status' => 'upheld',
            'resolved_by_id' => $moderator->id,
            'resolved_at' => now(),
            'resolution_notes' => 'Verified violation',
        ]);

        Activity::query()->delete();

        ReportResolved::dispatch($report->fresh(), $moderator, 'upheld');

        $activities = Activity::where('log_name', 'moderation')->get();

        // Should have 2 entries: one on report, one on reportable
        expect($activities)->toHaveCount(2);

        $reportActivity = $activities->firstWhere('subject_type', 'report');
        expect($reportActivity)->not->toBeNull()
            ->and($reportActivity->description)->toContain('Report upheld')
            ->and($reportActivity->event)->toBe('report_upheld');

        $reportableActivity = $activities->firstWhere('event', 'report_upheld')
            ->where('subject_type', '!=', 'report');
    });

    it('logs activity when a report is dismissed', function () {
        $moderator = User::factory()->create();
        $report = Report::factory()->pending()->create();
        $report->update([
            'status' => 'dismissed',
            'resolved_by_id' => $moderator->id,
            'resolved_at' => now(),
            'resolution_notes' => 'Not a real issue',
        ]);

        Activity::query()->delete();

        ReportResolved::dispatch($report->fresh(), $moderator, 'dismissed');

        $reportActivity = Activity::where('event', 'report_dismissed')
            ->where('subject_type', 'report')
            ->where('log_name', 'moderation')
            ->first();

        expect($reportActivity)->not->toBeNull()
            ->and($reportActivity->description)->toContain('Report dismissed')
            ->and($reportActivity->causer_id)->toBe($moderator->id);
    });
});

describe('Revision events', function () {
    it('logs activity when a revision is submitted', function () {
        $revision = Revision::factory()->pending()->create([
            'proposed_changes' => ['bio' => 'New bio content'],
        ]);

        Activity::query()->delete();

        RevisionSubmitted::dispatch($revision);

        $activity = Activity::where('event', 'revision_submitted')
            ->where('log_name', 'moderation')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toContain('Revision submitted for')
            ->and($activity->subject_id)->toBe($revision->id)
            ->and($activity->properties)->toHaveKey('changes_count');
    });

    it('logs activity when a revision is approved', function () {
        $reviewer = User::factory()->create();
        $revision = Revision::factory()->pending()->create();

        Activity::query()->delete();

        RevisionApproved::dispatch($revision, $reviewer);

        $activity = Activity::where('event', 'revision_approved')
            ->where('log_name', 'moderation')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toBe('Revision approved')
            ->and($activity->causer_id)->toBe($reviewer->id)
            ->and($activity->subject_id)->toBe($revision->id);
    });

    it('logs activity when a revision is rejected', function () {
        $reviewer = User::factory()->create();
        $revision = Revision::factory()->pending()->create();

        Activity::query()->delete();

        RevisionRejected::dispatch($revision, $reviewer, 'Contains inappropriate content');

        $activity = Activity::where('event', 'revision_rejected')
            ->where('log_name', 'moderation')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toBe('Revision rejected: Contains inappropriate content')
            ->and($activity->causer_id)->toBe($reviewer->id)
            ->and($activity->properties['reason'])->toBe('Contains inappropriate content');
    });

    it('logs activity when a revision is auto-approved', function () {
        $revision = Revision::factory()->pending()->create();

        Activity::query()->delete();

        RevisionAutoApproved::dispatch($revision);

        $activity = Activity::where('event', 'revision_auto_approved')
            ->where('log_name', 'moderation')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->description)->toBe('Revision auto-approved')
            ->and($activity->subject_id)->toBe($revision->id);
    });
});

describe('No duplicate audit logs', function () {
    it('creates exactly one log entry when submitting a report via action', function () {
        $reporter = User::factory()->create();
        $report = Report::factory()->pending()->create(['reported_by_id' => $reporter->id]);
        // Delete the report so SubmitReport can create a new one
        $reportableType = $report->reportable_type;
        $reportableId = $report->reportable_id;
        $report->delete();

        Activity::query()->delete();

        Illuminate\Support\Facades\Notification::fake();

        $reportable = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($reportableType)::find($reportableId);

        $newReport = \CorvMC\Moderation\Actions\Reports\SubmitReport::run(
            $reportable,
            $reporter,
            'spam',
        );

        $logs = Activity::where('subject_type', 'report')
            ->where('subject_id', $newReport->id)
            ->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->log_name)->toBe('moderation')
            ->and($logs->first()->event)->toBe('report_submitted');
    });

    it('creates exactly one log entry on the report when resolving via action', function () {
        $moderator = User::factory()->withRole('admin')->create();
        $report = Report::factory()->pending()->create();

        Illuminate\Support\Facades\Notification::fake();
        Activity::query()->delete();

        \CorvMC\Moderation\Actions\Reports\ResolveReport::run(
            $report,
            $moderator,
            'upheld',
            'Verified violation',
        );

        $reportLogs = Activity::where('subject_type', 'report')
            ->where('subject_id', $report->id)
            ->get();

        expect($reportLogs)->toHaveCount(1)
            ->and($reportLogs->first()->log_name)->toBe('moderation')
            ->and($reportLogs->first()->event)->toBe('report_upheld');
    });
});

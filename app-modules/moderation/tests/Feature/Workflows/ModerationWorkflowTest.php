<?php

use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use CorvMC\Membership\Models\MemberProfile;
use App\Models\User;
use CorvMC\Events\Models\Venue;
use CorvMC\Moderation\Actions\Reports\ResolveReport;
use CorvMC\Moderation\Actions\Reports\SubmitReport;
use CorvMC\Moderation\Actions\Revisions\ApproveRevision;
use CorvMC\Moderation\Actions\Revisions\HandleRevisionSubmission;
use CorvMC\Moderation\Actions\Trust\AwardTrustPoints;
use CorvMC\Moderation\Enums\ReportStatus;
use CorvMC\Moderation\Facades\ReportService;
use CorvMC\Moderation\Facades\RevisionService;
use CorvMC\Moderation\Facades\TrustService;
use CorvMC\Moderation\Models\Report;
use CorvMC\Moderation\Models\Revision;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

describe('Moderation Workflow: Report Flow', function () {
    it('submits a report for an event', function () {
        $reporter = User::factory()->create();
        $organizer = User::factory()->create();

        // Create an event using the Event model directly
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        $report = ReportService::submitReport(
            $reporter,
            Event::class,
            $event->id,
            'inappropriate_content',
            'This event promotes inappropriate activities'
        );

        expect($report)->toBeInstanceOf(Report::class);
        expect($report->reportable_type)->toBe(Event::class);
        expect($report->reportable_id)->toBe($event->id);
        expect($report->reported_by_id)->toBe($reporter->id);
        expect($report->reason)->toBe('inappropriate_content');
        expect($report->custom_reason)->toBe('This event promotes inappropriate activities');
        expect($report->status)->toBe(ReportStatus::Pending);
    });

    it('prevents duplicate pending reports from same user', function () {
        $reporter = User::factory()->create();
        $event = Event::factory()->create();

        ReportService::submitReport($reporter, Event::class, $event->id, 'spam');

        expect(fn () => ReportService::submitReport($reporter, Event::class, $event->id, 'spam'))
            ->toThrow(\Exception::class, 'You have already reported this content');
    });

    it('resolves a report as upheld', function () {
        $reporter = User::factory()->create();
        $moderator = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $report = ReportService::submitReport($reporter, Event::class, $event->id, 'misleading_info');
        expect($report->status)->toBe(ReportStatus::Pending);

        $resolvedReport = ReportService::resolveReport(
            $report,
            $moderator,
            'removed',
            'Content violates community guidelines'
        );

        expect($resolvedReport->status)->toBe(ReportStatus::Upheld);
        expect($resolvedReport->resolved_by_id)->toBe($moderator->id);
        expect($resolvedReport->resolved_at)->not->toBeNull();
        expect($resolvedReport->resolution_notes)->toBe('Content violates community guidelines');
    });

    it('resolves a report as dismissed', function () {
        $reporter = User::factory()->create();
        $moderator = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $report = ReportService::submitReport($reporter, Event::class, $event->id, 'spam');

        $resolvedReport = ReportService::resolveReport(
            $report,
            $moderator,
            'dismissed',
            'Report does not meet criteria'
        );

        expect($resolvedReport->status)->toBe(ReportStatus::Dismissed);
    });
});

describe('Moderation Workflow: Trust Points', function () {
    it('awards trust points to a user', function () {
        $user = User::factory()->create();

        $transaction = TrustService::awardPoints(
            user: $user,
            points: 5,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'Event approved without issues'
        );

        expect($transaction)->not->toBeNull();
        expect($transaction->points)->toBe(5);
        expect($transaction->balance_after)->toBe(5);
        expect($transaction->reason)->toBe('Event approved without issues');
    });

    it('accumulates trust points over multiple awards', function () {
        $user = User::factory()->create();

        TrustService::awardPoints(
            user: $user,
            points: 3,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'First event'
        );

        $secondTransaction = TrustService::awardPoints(
            user: $user,
            points: 5,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'Second event'
        );

        expect($secondTransaction->balance_after)->toBe(8);
    });

    it('prevents trust balance going below zero for specific content types', function () {
        $user = User::factory()->create();

        // Award some points first
        TrustService::awardPoints(
            user: $user,
            points: 2,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'Initial points'
        );

        // Deduct more than available
        $transaction = TrustService::awardPoints(
            user: $user,
            points: -10,
            contentType: Event::class,
            sourceType: 'violation',
            reason: 'Policy violation'
        );

        // Should be capped at 0, not negative
        expect($transaction->balance_after)->toBe(0);
    });

    it('tracks different content types separately', function () {
        $user = User::factory()->create();

        TrustService::awardPoints(
            user: $user,
            points: 10,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'Event trust'
        );

        TrustService::awardPoints(
            user: $user,
            points: 5,
            contentType: Band::class,
            sourceType: 'content_approved',
            reason: 'Band trust'
        );

        // Each content type should have its own balance
        expect($user->getTrustBalance(Event::class))->toBe(10);
        expect($user->getTrustBalance(Band::class))->toBe(5);
    });
});

describe('Moderation Workflow: Revision Approval', function () {
    it('approves a pending revision and applies changes', function () {
        $submitter = User::withoutEvents(fn() => User::factory()->create());
        $reviewer = User::factory()->admin()->create();

        // Create a profile to revise
        $profile = MemberProfile::create([
            'user_id' => $submitter->id,
            'bio' => 'Original bio',
        ]);

        // Create a pending revision
        $revision = Revision::create([
            'revisionable_type' => MemberProfile::class,
            'revisionable_id' => $profile->id,
            'original_data' => ['bio' => 'Original bio'],
            'proposed_changes' => ['bio' => 'Updated bio with more details'],
            'status' => Revision::STATUS_PENDING,
            'submitted_by_id' => $submitter->id,
            'revision_type' => 'update',
        ]);

        $revision = RevisionService::approve($revision, $reviewer, 'Good update');

        expect($revision)->toBeInstanceOf(Revision::class);

        $revision->refresh();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->approved_by)->toBe($reviewer->id);
        expect($revision->approved_at)->not->toBeNull();

        // Changes should be applied to the profile
        $profile->refresh();
        expect($profile->bio)->toBe('Updated bio with more details');
    });

    it('throws exception when approving already reviewed revision', function () {
        $submitter = User::withoutEvents(fn() => User::factory()->create());
        $reviewer = User::factory()->admin()->create();

        $profile = MemberProfile::create([
            'user_id' => $submitter->id,
            'bio' => 'Test bio',
        ]);

        // Create an already approved revision
        $revision = Revision::create([
            'revisionable_type' => MemberProfile::class,
            'revisionable_id' => $profile->id,
            'original_data' => ['bio' => 'Test bio'],
            'proposed_changes' => ['bio' => 'New bio'],
            'status' => Revision::STATUS_APPROVED,
            'submitted_by_id' => $submitter->id,
            'revision_type' => 'update',
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);

        expect(fn () => RevisionService::approve($revision, $reviewer))
            ->toThrow(\Exception::class);
    });
});

describe('Moderation Workflow: Auto-Approval', function () {
    it('auto-approves revision for users with production manager role', function () {
        $submitter = User::withoutEvents(fn() => User::factory()->create());
        $submitter->assignRole('production manager');

        $profile = MemberProfile::create([
            'user_id' => $submitter->id,
            'bio' => 'Original bio',
        ]);

        $data = [
            'revisionable_type' => MemberProfile::class,
            'revisionable_id' => $profile->id,
            'original_data' => ['bio' => 'Original bio'],
            'proposed_changes' => ['bio' => 'Staff updated bio'],
            'revision_type' => 'update',
        ];

        $revision = RevisionService::handleSubmission($data, $submitter);

        $revision->refresh();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);

        // Changes should be applied
        $profile->refresh();
        expect($profile->bio)->toBe('Staff updated bio');
    });
});

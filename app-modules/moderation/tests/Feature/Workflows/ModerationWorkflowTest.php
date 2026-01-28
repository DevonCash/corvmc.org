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

        $report = SubmitReport::run(
            $event,
            $reporter,
            'inappropriate_content',
            'This event promotes inappropriate activities'
        );

        expect($report)->toBeInstanceOf(Report::class);
        expect($report->reportable_type)->toBe(Event::class);
        expect($report->reportable_id)->toBe($event->id);
        expect($report->reported_by_id)->toBe($reporter->id);
        expect($report->reason)->toBe('inappropriate_content');
        expect($report->custom_reason)->toBe('This event promotes inappropriate activities');
        expect($report->status)->toBe('pending');
    });

    it('prevents duplicate pending reports from same user', function () {
        $reporter = User::factory()->create();
        $event = Event::factory()->create();

        SubmitReport::run($event, $reporter, 'spam');

        expect(fn () => SubmitReport::run($event, $reporter, 'spam'))
            ->toThrow(\Exception::class, 'You have already reported this content');
    });

    it('resolves a report as upheld', function () {
        $reporter = User::factory()->create();
        $moderator = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $report = SubmitReport::run($event, $reporter, 'misleading_info');
        expect($report->status)->toBe('pending');

        $resolvedReport = ResolveReport::run(
            $report,
            $moderator,
            'upheld',
            'Content violates community guidelines'
        );

        expect($resolvedReport->status)->toBe('upheld');
        expect($resolvedReport->resolved_by_id)->toBe($moderator->id);
        expect($resolvedReport->resolved_at)->not->toBeNull();
        expect($resolvedReport->resolution_notes)->toBe('Content violates community guidelines');
    });

    it('resolves a report as dismissed', function () {
        $reporter = User::factory()->create();
        $moderator = User::factory()->admin()->create();
        $event = Event::factory()->create();

        $report = SubmitReport::run($event, $reporter, 'spam');

        $resolvedReport = ResolveReport::run(
            $report,
            $moderator,
            'dismissed',
            'Report does not meet criteria'
        );

        expect($resolvedReport->status)->toBe('dismissed');
    });
});

describe('Moderation Workflow: Trust Points', function () {
    it('awards trust points to a user', function () {
        $user = User::factory()->create();

        $transaction = AwardTrustPoints::run(
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

        AwardTrustPoints::run(
            user: $user,
            points: 3,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'First event'
        );

        $secondTransaction = AwardTrustPoints::run(
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
        AwardTrustPoints::run(
            user: $user,
            points: 2,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'Initial points'
        );

        // Deduct more than available
        $transaction = AwardTrustPoints::run(
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

        AwardTrustPoints::run(
            user: $user,
            points: 10,
            contentType: Event::class,
            sourceType: 'content_approved',
            reason: 'Event trust'
        );

        AwardTrustPoints::run(
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
        $submitter = User::factory()->create();
        $reviewer = User::factory()->admin()->create();

        // Create a profile to revise
        $profile = User::withoutEvents(function () use ($submitter) {
            return MemberProfile::create([
                'user_id' => $submitter->id,
                'bio' => 'Original bio',
            ]);
        });

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

        $result = ApproveRevision::run($revision, $reviewer, 'Good update');

        expect($result)->toBeTrue();

        $revision->refresh();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->reviewed_by_id)->toBe($reviewer->id);
        expect($revision->reviewed_at)->not->toBeNull();

        // Changes should be applied to the profile
        $profile->refresh();
        expect($profile->bio)->toBe('Updated bio with more details');
    });

    it('throws exception when approving already reviewed revision', function () {
        $submitter = User::factory()->create();
        $reviewer = User::factory()->admin()->create();

        $profile = User::withoutEvents(function () use ($submitter) {
            return MemberProfile::create([
                'user_id' => $submitter->id,
                'bio' => 'Test bio',
            ]);
        });

        // Create an already approved revision
        $revision = Revision::create([
            'revisionable_type' => MemberProfile::class,
            'revisionable_id' => $profile->id,
            'original_data' => ['bio' => 'Test bio'],
            'proposed_changes' => ['bio' => 'New bio'],
            'status' => Revision::STATUS_APPROVED,
            'submitted_by_id' => $submitter->id,
            'revision_type' => 'update',
            'reviewed_by_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        expect(fn () => ApproveRevision::run($revision, $reviewer))
            ->toThrow(\InvalidArgumentException::class, 'Revision is not pending approval');
    });
});

describe('Moderation Workflow: Auto-Approval', function () {
    it('auto-approves revision for users with production manager role', function () {
        $submitter = User::factory()->create();
        $submitter->assignRole('production manager');

        $profile = User::withoutEvents(function () use ($submitter) {
            return MemberProfile::create([
                'user_id' => $submitter->id,
                'bio' => 'Original bio',
            ]);
        });

        $revision = Revision::create([
            'revisionable_type' => MemberProfile::class,
            'revisionable_id' => $profile->id,
            'original_data' => ['bio' => 'Original bio'],
            'proposed_changes' => ['bio' => 'Staff updated bio'],
            'status' => Revision::STATUS_PENDING,
            'submitted_by_id' => $submitter->id,
            'revision_type' => 'update',
        ]);

        HandleRevisionSubmission::run($revision);

        $revision->refresh();
        expect($revision->status)->toBe(Revision::STATUS_APPROVED);
        expect($revision->auto_approved)->toBeTrue();

        // Changes should be applied
        $profile->refresh();
        expect($profile->bio)->toBe('Staff updated bio');
    });
});

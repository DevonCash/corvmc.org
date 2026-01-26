<?php

use App\Models\User;
use App\Policies\ReportPolicy;
use CorvMC\Moderation\Models\Report;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new ReportPolicy();
});

describe('manage', function () {
    it('allows admin to manage reports', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->manage($admin))->toBeTrue();
    });

    it('allows moderator to manage reports', function () {
        $moderator = User::factory()->withRole('moderator')->create();

        expect($this->policy->manage($moderator))->toBeTrue();
    });

    it('denies regular members from managing reports', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any authenticated user to view reports list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows admin to view any report', function () {
        $admin = User::factory()->withRole('admin')->create();
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->view($admin, $report))->toBeTrue();
    });

    it('allows moderator to view any report', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->view($moderator, $report))->toBeTrue();
    });

    it('allows reporter to view their own report', function () {
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->view($reporter, $report))->toBeTrue();
    });

    it('denies non-reporters from viewing others reports', function () {
        $reporter = User::factory()->create();
        $otherUser = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->view($otherUser, $report))->toBeFalse();
    });
});

describe('create', function () {
    it('allows any authenticated user to create reports', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeTrue();
    });
});

describe('update', function () {
    it('allows admin to update reports', function () {
        $admin = User::factory()->withRole('admin')->create();
        $report = Report::factory()->create();

        expect($this->policy->update($admin, $report))->toBeTrue();
    });

    it('allows moderator to update reports', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $report = Report::factory()->create();

        expect($this->policy->update($moderator, $report))->toBeTrue();
    });

    it('denies reporter from updating their own report', function () {
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->update($reporter, $report))->toBeFalse();
    });

    it('denies regular members from updating reports', function () {
        $member = User::factory()->create();
        $report = Report::factory()->create();

        expect($this->policy->update($member, $report))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows admin to delete reports', function () {
        $admin = User::factory()->withRole('admin')->create();
        $report = Report::factory()->create();

        expect($this->policy->delete($admin, $report))->toBeTrue();
    });

    it('allows moderator to delete reports', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $report = Report::factory()->create();

        expect($this->policy->delete($moderator, $report))->toBeTrue();
    });

    it('denies reporter from deleting their own report', function () {
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->delete($reporter, $report))->toBeFalse();
    });

    it('denies regular members from deleting reports', function () {
        $member = User::factory()->create();
        $report = Report::factory()->create();

        expect($this->policy->delete($member, $report))->toBeFalse();
    });
});

describe('restore', function () {
    it('denies everyone from restoring reports', function () {
        $admin = User::factory()->withRole('admin')->create();
        $moderator = User::factory()->withRole('moderator')->create();
        $report = Report::factory()->create();

        expect($this->policy->restore($admin, $report))->toBeFalse();
        expect($this->policy->restore($moderator, $report))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting reports', function () {
        $admin = User::factory()->withRole('admin')->create();
        $moderator = User::factory()->withRole('moderator')->create();
        $report = Report::factory()->create();

        expect($this->policy->forceDelete($admin, $report))->toBeFalse();
        expect($this->policy->forceDelete($moderator, $report))->toBeFalse();
    });
});

describe('resolve', function () {
    it('allows admin to resolve reports', function () {
        $admin = User::factory()->withRole('admin')->create();
        $report = Report::factory()->create();

        expect($this->policy->resolve($admin, $report))->toBeTrue();
    });

    it('allows moderator to resolve reports', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $report = Report::factory()->create();

        expect($this->policy->resolve($moderator, $report))->toBeTrue();
    });

    it('denies reporter from resolving their own report', function () {
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->resolve($reporter, $report))->toBeFalse();
    });
});

describe('escalate', function () {
    it('allows admin to escalate reports', function () {
        $admin = User::factory()->withRole('admin')->create();
        $report = Report::factory()->create();

        expect($this->policy->escalate($admin, $report))->toBeTrue();
    });

    it('allows moderator to escalate reports', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $report = Report::factory()->create();

        expect($this->policy->escalate($moderator, $report))->toBeTrue();
    });

    it('denies reporter from escalating their own report', function () {
        $reporter = User::factory()->create();
        $report = Report::factory()->create([
            'reported_by_id' => $reporter->id,
        ]);

        expect($this->policy->escalate($reporter, $report))->toBeFalse();
    });
});

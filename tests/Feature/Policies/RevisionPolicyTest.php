<?php

use App\Models\User;
use App\Policies\RevisionPolicy;
use CorvMC\Moderation\Models\Revision;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new RevisionPolicy();
});

describe('manage', function () {
    it('allows admin to manage revisions', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->manage($admin))->toBeTrue();
    });

    it('allows moderator to manage revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();

        expect($this->policy->manage($moderator))->toBeTrue();
    });

    it('denies regular members from managing revisions', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any authenticated user to view revisions list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows admin to view any revision', function () {
        $admin = User::factory()->withRole('admin')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->view($admin, $revision))->toBeTrue();
    });

    it('allows moderator to view any revision', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->view($moderator, $revision))->toBeTrue();
    });

    it('allows submitter to view their own revision', function () {
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->view($submitter, $revision))->toBeTrue();
    });

    it('denies non-submitters from viewing others revisions', function () {
        $submitter = User::factory()->create();
        $otherUser = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->view($otherUser, $revision))->toBeFalse();
    });
});

describe('create', function () {
    it('allows any authenticated user to create revisions', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeTrue();
    });
});

describe('update', function () {
    it('allows admin to update pending revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $revision = Revision::factory()->pending()->create();

        expect($this->policy->update($admin, $revision))->toBeTrue();
    });

    it('allows moderator to update pending revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $revision = Revision::factory()->pending()->create();

        expect($this->policy->update($moderator, $revision))->toBeTrue();
    });

    it('allows submitter to update their own pending revision', function () {
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->update($submitter, $revision))->toBeTrue();
    });

    it('denies non-submitters from updating pending revisions', function () {
        $submitter = User::factory()->create();
        $otherUser = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->update($otherUser, $revision))->toBeFalse();
    });

    it('denies anyone from updating approved revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->approved()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->update($admin, $revision))->toBeFalse();
        expect($this->policy->update($submitter, $revision))->toBeFalse();
    });

    it('denies anyone from updating rejected revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->rejected()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->update($admin, $revision))->toBeFalse();
        expect($this->policy->update($submitter, $revision))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows admin to delete pending revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $revision = Revision::factory()->pending()->create();

        expect($this->policy->delete($admin, $revision))->toBeTrue();
    });

    it('allows moderator to delete pending revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $revision = Revision::factory()->pending()->create();

        expect($this->policy->delete($moderator, $revision))->toBeTrue();
    });

    it('allows submitter to delete their own pending revision', function () {
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->delete($submitter, $revision))->toBeTrue();
    });

    it('denies non-submitters from deleting pending revisions', function () {
        $submitter = User::factory()->create();
        $otherUser = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->delete($otherUser, $revision))->toBeFalse();
    });

    it('denies anyone from deleting approved revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->approved()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->delete($admin, $revision))->toBeFalse();
        expect($this->policy->delete($submitter, $revision))->toBeFalse();
    });

    it('denies anyone from deleting rejected revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->rejected()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->delete($admin, $revision))->toBeFalse();
        expect($this->policy->delete($submitter, $revision))->toBeFalse();
    });
});

describe('restore', function () {
    it('denies everyone from restoring revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $moderator = User::factory()->withRole('moderator')->create();
        $revision = Revision::factory()->pending()->create();

        expect($this->policy->restore($admin, $revision))->toBeFalse();
        expect($this->policy->restore($moderator, $revision))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $moderator = User::factory()->withRole('moderator')->create();
        $revision = Revision::factory()->pending()->create();

        expect($this->policy->forceDelete($admin, $revision))->toBeFalse();
        expect($this->policy->forceDelete($moderator, $revision))->toBeFalse();
    });
});

describe('approve', function () {
    it('allows admin to approve pending revisions they did not submit', function () {
        $admin = User::factory()->withRole('admin')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->approve($admin, $revision))->toBeTrue();
    });

    it('allows moderator to approve pending revisions they did not submit', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->approve($moderator, $revision))->toBeTrue();
    });

    it('denies moderator from approving their own revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $moderator->id,
        ]);

        expect($this->policy->approve($moderator, $revision))->toBeFalse();
    });

    it('denies admin from approving their own revisions', function () {
        $admin = User::factory()->withRole('admin')->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $admin->id,
        ]);

        expect($this->policy->approve($admin, $revision))->toBeFalse();
    });

    it('denies regular members from approving revisions', function () {
        $member = User::factory()->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->approve($member, $revision))->toBeFalse();
    });

    it('denies approving already approved revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->approved()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->approve($moderator, $revision))->toBeFalse();
    });

    it('denies approving rejected revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->rejected()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->approve($moderator, $revision))->toBeFalse();
    });
});

describe('reject', function () {
    it('allows moderator to reject pending revisions they did not submit', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->reject($moderator, $revision))->toBeTrue();
    });

    it('denies moderator from rejecting their own revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $moderator->id,
        ]);

        expect($this->policy->reject($moderator, $revision))->toBeFalse();
    });

    it('denies regular members from rejecting revisions', function () {
        $member = User::factory()->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->pending()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->reject($member, $revision))->toBeFalse();
    });

    it('denies rejecting already approved revisions', function () {
        $moderator = User::factory()->withRole('moderator')->create();
        $submitter = User::factory()->create();
        $revision = Revision::factory()->approved()->create([
            'submitted_by_id' => $submitter->id,
        ]);

        expect($this->policy->reject($moderator, $revision))->toBeFalse();
    });
});

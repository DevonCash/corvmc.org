<?php

use App\Models\User;
use App\Policies\SponsorPolicy;
use CorvMC\Sponsorship\Models\Sponsor;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new SponsorPolicy();
});

describe('manage', function () {
    it('allows admin to manage sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->manage($admin))->toBeTrue();
    });

    it('denies regular members from managing sponsors', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies moderator from managing sponsors', function () {
        $moderator = User::factory()->withRole('moderator')->create();

        expect($this->policy->manage($moderator))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows anyone (including guests) to view sponsors list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
        expect($this->policy->viewAny(null))->toBeTrue();
    });
});

describe('view', function () {
    it('allows anyone (including guests) to view individual sponsor', function () {
        $sponsor = Sponsor::factory()->create();
        $member = User::factory()->create();

        expect($this->policy->view($member, $sponsor))->toBeTrue();
        expect($this->policy->view(null, $sponsor))->toBeTrue();
    });
});

describe('create', function () {
    it('allows admin to create sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->create($admin))->toBeTrue();
    });

    it('denies regular members from creating sponsors', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeFalse();
    });
});

describe('update', function () {
    it('allows admin to update sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->update($admin, $sponsor))->toBeTrue();
    });

    it('denies regular members from updating sponsors', function () {
        $member = User::factory()->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->update($member, $sponsor))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows admin to delete sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->delete($admin, $sponsor))->toBeTrue();
    });

    it('denies regular members from deleting sponsors', function () {
        $member = User::factory()->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->delete($member, $sponsor))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows admin to restore sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->restore($admin, $sponsor))->toBeTrue();
    });

    it('denies regular members from restoring sponsors', function () {
        $member = User::factory()->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->restore($member, $sponsor))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();
        $member = User::factory()->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->forceDelete($admin, $sponsor))->toBeFalse();
        expect($this->policy->forceDelete($member, $sponsor))->toBeFalse();
    });
});

describe('attachUser', function () {
    it('allows admin to attach users to sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->attachUser($admin, $sponsor))->toBeTrue();
    });

    it('denies regular members from attaching users to sponsors', function () {
        $member = User::factory()->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->attachUser($member, $sponsor))->toBeFalse();
    });
});

describe('detachUser', function () {
    it('allows admin to detach users from sponsors', function () {
        $admin = User::factory()->withRole('admin')->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->detachUser($admin, $sponsor))->toBeTrue();
    });

    it('denies regular members from detaching users from sponsors', function () {
        $member = User::factory()->create();
        $sponsor = Sponsor::factory()->create();

        expect($this->policy->detachUser($member, $sponsor))->toBeFalse();
    });
});

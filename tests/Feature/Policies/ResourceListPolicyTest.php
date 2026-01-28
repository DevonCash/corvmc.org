<?php

use App\Models\ResourceList;
use App\Models\User;
use App\Policies\ResourceListPolicy;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new ResourceListPolicy();
});

describe('manage', function () {
    it('allows admin to manage resource lists', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->manage($admin))->toBeTrue();
    });

    it('denies regular members from managing resource lists', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies moderator from managing resource lists', function () {
        $moderator = User::factory()->withRole('moderator')->create();

        expect($this->policy->manage($moderator))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows anyone (including guests) to view resource lists', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
        expect($this->policy->viewAny(null))->toBeTrue();
    });
});

describe('view', function () {
    it('allows anyone (including guests) to view individual resource list', function () {
        $resourceList = ResourceList::factory()->create();
        $member = User::factory()->create();

        expect($this->policy->view($member, $resourceList))->toBeTrue();
        expect($this->policy->view(null, $resourceList))->toBeTrue();
    });
});

describe('create', function () {
    it('allows admin to create resource lists', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->create($admin))->toBeTrue();
    });

    it('denies regular members from creating resource lists', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeFalse();
    });
});

describe('update', function () {
    it('allows admin to update resource lists', function () {
        $admin = User::factory()->withRole('admin')->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->update($admin, $resourceList))->toBeTrue();
    });

    it('denies regular members from updating resource lists', function () {
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->update($member, $resourceList))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows admin to delete resource lists', function () {
        $admin = User::factory()->withRole('admin')->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->delete($admin, $resourceList))->toBeTrue();
    });

    it('denies regular members from deleting resource lists', function () {
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->delete($member, $resourceList))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows admin to restore resource lists', function () {
        $admin = User::factory()->withRole('admin')->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->restore($admin, $resourceList))->toBeTrue();
    });

    it('denies regular members from restoring resource lists', function () {
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->restore($member, $resourceList))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting resource lists', function () {
        $admin = User::factory()->withRole('admin')->create();
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();

        expect($this->policy->forceDelete($admin, $resourceList))->toBeFalse();
        expect($this->policy->forceDelete($member, $resourceList))->toBeFalse();
    });
});

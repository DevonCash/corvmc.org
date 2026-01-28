<?php

use App\Models\LocalResource;
use App\Models\ResourceList;
use App\Models\User;
use App\Policies\LocalResourcePolicy;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new LocalResourcePolicy();
});

describe('manage', function () {
    it('allows admin to manage local resources', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->manage($admin))->toBeTrue();
    });

    it('denies regular members from managing local resources', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies moderator from managing local resources', function () {
        $moderator = User::factory()->withRole('moderator')->create();

        expect($this->policy->manage($moderator))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows anyone (including guests) to view local resources', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
        expect($this->policy->viewAny(null))->toBeTrue();
    });
});

describe('view', function () {
    it('allows anyone (including guests) to view individual local resource', function () {
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();
        $member = User::factory()->create();

        expect($this->policy->view($member, $localResource))->toBeTrue();
        expect($this->policy->view(null, $localResource))->toBeTrue();
    });
});

describe('create', function () {
    it('allows admin to create local resources', function () {
        $admin = User::factory()->withRole('admin')->create();

        expect($this->policy->create($admin))->toBeTrue();
    });

    it('denies regular members from creating local resources', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeFalse();
    });
});

describe('update', function () {
    it('allows admin to update local resources', function () {
        $admin = User::factory()->withRole('admin')->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->update($admin, $localResource))->toBeTrue();
    });

    it('denies regular members from updating local resources', function () {
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->update($member, $localResource))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows admin to delete local resources', function () {
        $admin = User::factory()->withRole('admin')->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->delete($admin, $localResource))->toBeTrue();
    });

    it('denies regular members from deleting local resources', function () {
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->delete($member, $localResource))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows admin to restore local resources', function () {
        $admin = User::factory()->withRole('admin')->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->restore($admin, $localResource))->toBeTrue();
    });

    it('denies regular members from restoring local resources', function () {
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->restore($member, $localResource))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting local resources', function () {
        $admin = User::factory()->withRole('admin')->create();
        $member = User::factory()->create();
        $resourceList = ResourceList::factory()->create();
        $localResource = LocalResource::factory()->forList($resourceList)->create();

        expect($this->policy->forceDelete($admin, $localResource))->toBeFalse();
        expect($this->policy->forceDelete($member, $localResource))->toBeFalse();
    });
});

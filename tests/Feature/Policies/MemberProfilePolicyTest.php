<?php

use App\Models\User;
use App\Policies\MemberProfilePolicy;
use CorvMC\Membership\Data\ContactData;
use CorvMC\Membership\Models\MemberProfile;
use CorvMC\Moderation\Enums\Visibility;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new MemberProfilePolicy();
});

describe('manage', function () {
    it('allows directory moderator to manage profiles', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();

        expect($this->policy->manage($moderator))->toBeTrue();
    });

    it('denies regular members from managing profiles', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies profile owners from managing all profiles', function () {
        $owner = User::factory()->create();

        expect($this->policy->manage($owner))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any authenticated user to view profiles list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows anyone (including guests) to view public profiles', function () {
        $profile = MemberProfile::factory()->public()->create();

        // Authenticated user
        $viewer = User::factory()->create();
        expect($this->policy->view($viewer, $profile))->toBeTrue();

        // Guest (null user)
        expect($this->policy->view(null, $profile))->toBeTrue();
    });

    it('allows directory moderator to view any profile', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $profile = MemberProfile::factory()->private()->create();

        expect($this->policy->view($moderator, $profile))->toBeTrue();
    });

    it('allows owner to view their own private profile', function () {
        $owner = User::factory()->create();
        $profile = MemberProfile::factory()->private()->create([
            'user_id' => $owner->id,
        ]);

        expect($this->policy->view($owner, $profile))->toBeTrue();
    });

    it('allows any logged-in user to view members-only profile', function () {
        $profile = MemberProfile::factory()->membersOnly()->create();
        $viewer = User::factory()->create();

        expect($this->policy->view($viewer, $profile))->toBeTrue();
    });

    it('denies guests from viewing members-only profiles', function () {
        $profile = MemberProfile::factory()->membersOnly()->create();

        expect($this->policy->view(null, $profile))->toBeFalse();
    });

    it('denies outsiders from viewing private profiles', function () {
        $profile = MemberProfile::factory()->private()->create();
        $outsider = User::factory()->create();

        expect($this->policy->view($outsider, $profile))->toBeFalse();
    });

    it('denies guests from viewing private profiles', function () {
        $profile = MemberProfile::factory()->private()->create();

        expect($this->policy->view(null, $profile))->toBeFalse();
    });
});

describe('create', function () {
    it('allows any authenticated user to create profiles', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeTrue();
    });
});

describe('update', function () {
    it('allows directory moderator to update any profile', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $profile = MemberProfile::factory()->create();

        expect($this->policy->update($moderator, $profile))->toBeTrue();
    });

    it('allows owner to update their own profile', function () {
        $owner = User::factory()->create();
        $profile = MemberProfile::factory()->create([
            'user_id' => $owner->id,
        ]);

        expect($this->policy->update($owner, $profile))->toBeTrue();
    });

    it('denies non-owner from updating another users profile', function () {
        $profile = MemberProfile::factory()->create();
        $otherUser = User::factory()->create();

        expect($this->policy->update($otherUser, $profile))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows directory moderator to delete any profile', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $profile = MemberProfile::factory()->create();

        expect($this->policy->delete($moderator, $profile))->toBeTrue();
    });

    it('allows owner to delete their own profile', function () {
        $owner = User::factory()->create();
        $profile = MemberProfile::factory()->create([
            'user_id' => $owner->id,
        ]);

        expect($this->policy->delete($owner, $profile))->toBeTrue();
    });

    it('denies non-owner from deleting another users profile', function () {
        $profile = MemberProfile::factory()->create();
        $otherUser = User::factory()->create();

        expect($this->policy->delete($otherUser, $profile))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows directory moderator to restore any profile', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $profile = MemberProfile::factory()->create();

        expect($this->policy->restore($moderator, $profile))->toBeTrue();
    });

    it('allows owner to restore their own profile', function () {
        $owner = User::factory()->create();
        $profile = MemberProfile::factory()->create([
            'user_id' => $owner->id,
        ]);

        expect($this->policy->restore($owner, $profile))->toBeTrue();
    });

    it('denies non-owner from restoring another users profile', function () {
        $profile = MemberProfile::factory()->create();
        $otherUser = User::factory()->create();

        expect($this->policy->restore($otherUser, $profile))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting profiles', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $profile = MemberProfile::factory()->create([
            'user_id' => $owner->id,
        ]);

        expect($this->policy->forceDelete($moderator, $profile))->toBeFalse();
        expect($this->policy->forceDelete($owner, $profile))->toBeFalse();
    });
});

describe('viewContact', function () {
    it('allows anyone to view public contact info', function () {
        $profile = MemberProfile::factory()->create([
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Public,
            ]),
        ]);

        $viewer = User::factory()->create();
        expect($this->policy->viewContact($viewer, $profile))->toBeTrue();
        expect($this->policy->viewContact(null, $profile))->toBeTrue();
    });

    it('allows logged-in users to view members-only contact info', function () {
        $profile = MemberProfile::factory()->create([
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Members,
            ]),
        ]);

        $viewer = User::factory()->create();
        expect($this->policy->viewContact($viewer, $profile))->toBeTrue();
    });

    it('denies guests from viewing members-only contact info', function () {
        $profile = MemberProfile::factory()->create([
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Members,
            ]),
        ]);

        expect($this->policy->viewContact(null, $profile))->toBeFalse();
    });

    it('allows owner to view their own private contact info', function () {
        $owner = User::factory()->create();
        $profile = MemberProfile::factory()->create([
            'user_id' => $owner->id,
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Private,
            ]),
        ]);

        expect($this->policy->viewContact($owner, $profile))->toBeTrue();
    });

    it('allows directory moderator to view private contact info', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $profile = MemberProfile::factory()->create([
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Private,
            ]),
        ]);

        expect($this->policy->viewContact($moderator, $profile))->toBeTrue();
    });

    it('denies non-owner from viewing private contact info', function () {
        $profile = MemberProfile::factory()->create([
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Private,
            ]),
        ]);
        $outsider = User::factory()->create();

        expect($this->policy->viewContact($outsider, $profile))->toBeFalse();
    });

    it('allows anyone to view when no contact visibility is set', function () {
        $profile = MemberProfile::factory()->create([
            'contact' => null,
        ]);

        expect($this->policy->viewContact(null, $profile))->toBeTrue();
    });
});

describe('policy via Gate', function () {
    it('allows calling manage via Gate without model instance', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $regularUser = User::factory()->create();

        expect($moderator->can('manage', MemberProfile::class))->toBeTrue();
        expect($regularUser->can('manage', MemberProfile::class))->toBeFalse();
    });
});

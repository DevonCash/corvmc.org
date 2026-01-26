<?php

use App\Models\User;
use App\Policies\BandPolicy;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Data\ContactData;
use CorvMC\Moderation\Enums\Visibility;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new BandPolicy();
});

describe('manage', function () {
    it('allows directory moderator to manage bands', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();

        expect($this->policy->manage($moderator))->toBeTrue();
    });

    it('denies regular members from managing bands', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies band owners from managing all bands', function () {
        $owner = User::factory()->create();

        expect($this->policy->manage($owner))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any authenticated user to view bands list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows anyone (including guests) to view public bands', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Public,
        ]);

        // Authenticated user
        $viewer = User::factory()->create();
        expect($this->policy->view($viewer, $band))->toBeTrue();

        // Guest (null user)
        expect($this->policy->view(null, $band))->toBeTrue();
    });

    it('allows directory moderator to view any band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Private,
        ]);

        expect($this->policy->view($moderator, $band))->toBeTrue();
    });

    it('allows owner to view their own private band', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Private,
        ]);

        expect($this->policy->view($owner, $band))->toBeTrue();
    });

    it('allows active member to view private band', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Private,
        ]);

        // Add member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->view($member, $band))->toBeTrue();
    });

    it('allows any logged-in user to view members-only band', function () {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Members,
        ]);

        expect($this->policy->view($viewer, $band))->toBeTrue();
    });

    it('denies guests from viewing members-only bands', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Members,
        ]);

        expect($this->policy->view(null, $band))->toBeFalse();
    });

    it('denies outsiders from viewing private bands', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Private,
        ]);

        expect($this->policy->view($outsider, $band))->toBeFalse();
    });

    it('denies guests from viewing private bands', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => Visibility::Private,
        ]);

        expect($this->policy->view(null, $band))->toBeFalse();
    });
});

describe('create', function () {
    it('allows any authenticated user to create bands', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeTrue();
    });
});

describe('update', function () {
    it('allows directory moderator to update any band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->update($moderator, $band))->toBeTrue();
    });

    it('allows owner to update their own band', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->update($owner, $band))->toBeTrue();
    });

    it('allows admin member to update band', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add admin member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        expect($this->policy->update($admin, $band))->toBeTrue();
    });

    it('denies regular member from updating band', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add regular member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->update($member, $band))->toBeFalse();
    });

    it('denies outsiders from updating band', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->update($outsider, $band))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows directory moderator to delete any band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->delete($moderator, $band))->toBeTrue();
    });

    it('allows owner to delete their own band', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->delete($owner, $band))->toBeTrue();
    });

    it('denies admin member from deleting band', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add admin member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        expect($this->policy->delete($admin, $band))->toBeFalse();
    });

    it('denies regular members from deleting band', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add regular member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->delete($member, $band))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows directory moderator to restore any band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->restore($moderator, $band))->toBeTrue();
    });

    it('allows owner to restore their own band', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->restore($owner, $band))->toBeTrue();
    });

    it('denies admin member from restoring band', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add admin member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        expect($this->policy->restore($admin, $band))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies everyone from force deleting bands', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->forceDelete($moderator, $band))->toBeFalse();
        expect($this->policy->forceDelete($owner, $band))->toBeFalse();
    });
});

describe('invite', function () {
    it('allows directory moderator to invite to any band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->invite($moderator, $band))->toBeTrue();
    });

    it('allows owner to invite to their band', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->invite($owner, $band))->toBeTrue();
    });

    it('allows admin member to invite to band', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add admin member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        expect($this->policy->invite($admin, $band))->toBeTrue();
    });

    it('denies regular member from inviting to band', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add regular member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->invite($member, $band))->toBeFalse();
    });

    it('denies outsiders from inviting to band', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->invite($outsider, $band))->toBeFalse();
    });
});

describe('transfer', function () {
    it('allows owner to transfer band ownership', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->transfer($owner, $band))->toBeTrue();
    });

    it('denies directory moderator from transferring band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->transfer($moderator, $band))->toBeFalse();
    });

    it('denies admin member from transferring band', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add admin member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        expect($this->policy->transfer($admin, $band))->toBeFalse();
    });
});

describe('contact', function () {
    it('allows anyone to view public contact info', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Public,
            ]),
        ]);

        $viewer = User::factory()->create();
        expect($this->policy->contact($viewer, $band))->toBeTrue();
        expect($this->policy->contact(null, $band))->toBeTrue();
    });

    it('allows logged-in users to view members-only contact info', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Members,
            ]),
        ]);

        $viewer = User::factory()->create();
        expect($this->policy->contact($viewer, $band))->toBeTrue();
    });

    it('denies guests from viewing members-only contact info', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Members,
            ]),
        ]);

        expect($this->policy->contact(null, $band))->toBeFalse();
    });

    it('allows band members to view private contact info', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Private,
            ]),
        ]);

        // Add member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->contact($owner, $band))->toBeTrue();
        expect($this->policy->contact($member, $band))->toBeTrue();
    });

    it('denies non-members from viewing private contact info', function () {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'contact' => ContactData::from([
                'email' => 'test@example.com',
                'visibility' => Visibility::Private,
            ]),
        ]);

        expect($this->policy->contact($outsider, $band))->toBeFalse();
    });

    it('allows anyone to view when no contact visibility is set', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
            'contact' => null,
        ]);

        expect($this->policy->contact(null, $band))->toBeTrue();
    });
});

describe('join', function () {
    it('allows invited user to join band', function () {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Create invitation
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $invitee->id,
            'status' => 'invited',
        ]);

        expect($this->policy->join($invitee, $band))->toBeTrue();
    });

    it('denies non-invited user from joining band', function () {
        $owner = User::factory()->create();
        $nonInvitee = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->join($nonInvitee, $band))->toBeFalse();
    });

    it('denies active member from re-joining band', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Active member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'status' => 'active',
        ]);

        expect($this->policy->join($member, $band))->toBeFalse();
    });
});

describe('leave', function () {
    it('allows active member to leave band', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->leave($member, $band))->toBeTrue();
    });

    it('denies owner from leaving band', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->leave($owner, $band))->toBeFalse();
    });

    it('denies non-member from leaving band', function () {
        $owner = User::factory()->create();
        $nonMember = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->leave($nonMember, $band))->toBeFalse();
    });
});

describe('manageMembers', function () {
    it('allows directory moderator to manage members of any band', function () {
        $moderator = User::factory()->withRole('directory moderator')->create();
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->manageMembers($moderator, $band))->toBeTrue();
    });

    it('allows owner to manage members', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        expect($this->policy->manageMembers($owner, $band))->toBeTrue();
    });

    it('allows admin member to manage members', function () {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add admin member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        expect($this->policy->manageMembers($admin, $band))->toBeTrue();
    });

    it('denies regular member from managing members', function () {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $band = Band::factory()->create([
            'owner_id' => $owner->id,
        ]);

        // Add regular member
        BandMember::factory()->create([
            'band_profile_id' => $band->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        expect($this->policy->manageMembers($member, $band))->toBeFalse();
    });
});

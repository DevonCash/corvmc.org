<?php

use App\Models\StaffProfile;
use App\Models\User;
use App\Facades\StaffProfileService;

describe('Staff Profile Creation', function () {
    it('can create a staff profile', function () {
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'name' => 'John Doe',
            'title' => 'Operations Manager',
            'bio' => 'Experienced operations professional',
            'type' => 'staff',
            'is_active' => true,
            'email' => 'john@example.com',
            'sort_order' => 1,
        ];

        $staffProfile = StaffProfileService::createStaffProfile($data);

        expect($staffProfile)->toBeInstanceOf(StaffProfile::class)
            ->and($staffProfile->name)->toBe('John Doe')
            ->and($staffProfile->title)->toBe('Operations Manager')
            ->and($staffProfile->type->value)->toBe('staff')
            ->and($staffProfile->is_active)->toBeTrue()
            ->and($staffProfile->email)->toBe('john@example.com');
    });

    it('can create a board member profile', function () {
        $user = User::factory()->create();
        $data = [
            'user_id' => $user->id,
            'name' => 'Jane Smith',
            'title' => 'Board President',
            'type' => 'board',
            'bio' => 'Leading the organization forward',
            'is_active' => true,
            'sort_order' => 1,
        ];

        $staffProfile = StaffProfileService::createStaffProfile($data);

        expect($staffProfile->type->value)->toBe('board')
            ->and($staffProfile->title)->toBe('Board President');
    });
});

describe('Staff Profile Updates', function () {
    it('can update a staff profile', function () {
        // Authenticate an admin user who can update restricted fields
        $admin = User::factory()->create();
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);
        $this->actingAs($admin);

        $staffProfile = StaffProfile::factory()->create([
            'name' => 'Original Name',
            'title' => 'Original Title',
            'type' => 'staff',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'title' => 'Updated Title',
            'bio' => 'Updated bio information',
        ];

        $updatedProfile = StaffProfileService::updateStaffProfile($staffProfile, $updateData);

        expect($updatedProfile->name)->toBe('Updated Name')
            ->and($updatedProfile->title)->toBe('Updated Title')
            ->and($updatedProfile->bio)->toBe('Updated bio information');
    });

    it('returns fresh instance after update', function () {
        // Authenticate an admin user
        $admin = User::factory()->create();
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);
        $this->actingAs($admin);

        $staffProfile = StaffProfile::factory()->create(['name' => 'Original']);

        $result = StaffProfileService::updateStaffProfile($staffProfile, ['name' => 'Updated']);

        expect($result->id)->toBe($staffProfile->id)
            ->and($result->name)->toBe('Updated');
    });
});

describe('Staff Profile Deletion', function () {
    it('can delete a staff profile', function () {
        $staffProfile = StaffProfile::factory()->create();

        $result = StaffProfileService::deleteStaffProfile($staffProfile);

        expect($result)->toBeTrue()
            ->and(StaffProfile::find($staffProfile->id))->toBeNull();
    });
});

describe('Staff Profile Queries', function () {
    it('can get active staff profiles', function () {
        StaffProfile::factory()->active()->create(['type' => 'staff', 'sort_order' => 2]);
        StaffProfile::factory()->active()->create(['type' => 'board', 'sort_order' => 1]);
        StaffProfile::factory()->inactive()->create(['type' => 'staff']);

        $activeProfiles = StaffProfileService::getActiveStaffProfiles();

        expect($activeProfiles)->toHaveCount(2);

        foreach ($activeProfiles as $profile) {
            expect($profile->is_active)->toBeTrue();
        }
    });

    it('can filter active staff profiles by type', function () {
        StaffProfile::factory()->active()->staff()->create();
        StaffProfile::factory()->active()->board()->create();
        StaffProfile::factory()->active()->staff()->create();

        $staffProfiles = StaffProfileService::getActiveStaffProfiles('staff');
        $boardProfiles = StaffProfileService::getActiveStaffProfiles('board');

        expect($staffProfiles)->toHaveCount(2)
            ->and($boardProfiles)->toHaveCount(1);

        foreach ($staffProfiles as $profile) {
            expect($profile->type->value)->toBe('staff');
        }

        foreach ($boardProfiles as $profile) {
            expect($profile->type->value)->toBe('board');
        }
    });

    it('can get board members', function () {
        StaffProfile::factory()->active()->board()->create();
        StaffProfile::factory()->active()->board()->create();
        StaffProfile::factory()->active()->staff()->create(); // Should be excluded
        StaffProfile::factory()->inactive()->board()->create(); // Should be excluded

        $boardMembers = StaffProfileService::getBoardMembers();

        expect($boardMembers)->toHaveCount(2);

        foreach ($boardMembers as $member) {
            expect($member->type->value)->toBe('board')
                ->and($member->is_active)->toBeTrue();
        }
    });

    it('can get staff members', function () {
        StaffProfile::factory()->active()->staff()->create();
        StaffProfile::factory()->active()->staff()->create();
        StaffProfile::factory()->active()->board()->create(); // Should be excluded
        StaffProfile::factory()->inactive()->staff()->create(); // Should be excluded

        $staffMembers = StaffProfileService::getStaffMembers();

        expect($staffMembers)->toHaveCount(2);

        foreach ($staffMembers as $member) {
            expect($member->type->value)->toBe('staff')
                ->and($member->is_active)->toBeTrue();
        }
    });
});

describe('Staff Profile Ordering', function () {
    it('can reorder staff profiles', function () {
        $profile1 = StaffProfile::factory()->create(['sort_order' => 1]);
        $profile2 = StaffProfile::factory()->create(['sort_order' => 2]);
        $profile3 = StaffProfile::factory()->create(['sort_order' => 3]);

        // Reverse the order
        $newOrder = [$profile3->id, $profile1->id, $profile2->id];
        $result = StaffProfileService::reorderStaffProfiles($newOrder);

        expect($result)->toBeTrue();

        // Check new sort orders
        $profile1->refresh();
        $profile2->refresh();
        $profile3->refresh();

        expect($profile3->sort_order)->toBe(1)
            ->and($profile1->sort_order)->toBe(2)
            ->and($profile2->sort_order)->toBe(3);
    });
});

describe('Staff Profile Status Management', function () {
    it('can toggle active status from active to inactive', function () {
        $staffProfile = StaffProfile::factory()->active()->create();

        $result = StaffProfileService::toggleActiveStatus($staffProfile);

        expect($result)->toBeTrue();

        $staffProfile->refresh();
        expect($staffProfile->is_active)->toBeFalse();
    });

    it('can toggle active status from inactive to active', function () {
        $staffProfile = StaffProfile::factory()->inactive()->create();

        $result = StaffProfileService::toggleActiveStatus($staffProfile);

        expect($result)->toBeTrue();

        $staffProfile->refresh();
        expect($staffProfile->is_active)->toBeTrue();
    });
});

describe('Staff Profile Statistics', function () {
    it('can get staff profile statistics', function () {
        // Create test data
        StaffProfile::factory()->active()->staff()->create();
        StaffProfile::factory()->active()->staff()->create();
        StaffProfile::factory()->active()->board()->create();
        StaffProfile::factory()->inactive()->staff()->create();
        StaffProfile::factory()->inactive()->board()->create();

        $stats = StaffProfileService::getStaffProfileStats();

        expect($stats)->toHaveKeys(['total_profiles', 'active_profiles', 'board_members', 'staff_members'])
            ->and($stats['total_profiles'])->toBe(5)
            ->and($stats['active_profiles'])->toBe(3)
            ->and($stats['board_members'])->toBe(1)
            ->and($stats['staff_members'])->toBe(2);
    });
});

describe('User Account Linking', function () {
    it('can link staff profile to user account', function () {
        $oldUser = User::factory()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $oldUser->id]);
        $newUser = User::factory()->create();

        $result = StaffProfileService::linkToUser($staffProfile, $newUser);

        expect($result)->toBeTrue();

        $staffProfile->refresh();
        expect($staffProfile->user_id)->toBe($newUser->id);
    });

    it('can change user link to different user', function () {
        $originalUser = User::factory()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $originalUser->id]);
        $newUser = User::factory()->create();

        $result = StaffProfileService::linkToUser($staffProfile, $newUser);

        expect($result)->toBeTrue();

        $staffProfile->refresh();
        expect($staffProfile->user_id)->toBe($newUser->id);
    });

    it('can overwrite existing user link', function () {
        $oldUser = User::factory()->create();
        $newUser = User::factory()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $oldUser->id]);

        $result = StaffProfileService::linkToUser($staffProfile, $newUser);

        expect($result)->toBeTrue();

        $staffProfile->refresh();
        expect($staffProfile->user_id)->toBe($newUser->id);
    });
});

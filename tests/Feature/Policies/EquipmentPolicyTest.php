<?php

use App\Models\User;
use App\Policies\EquipmentPolicy;
use CorvMC\Equipment\Models\Equipment;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new EquipmentPolicy();
});

describe('manage', function () {
    it('allows equipment manager to manage equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();

        expect($this->policy->manage($manager))->toBeTrue();
    });

    it('denies regular members from managing equipment', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies sustaining members from managing equipment', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->manage($sustainingMember))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any user to view equipment list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });

    it('allows guests to view equipment list', function () {
        expect($this->policy->viewAny(null))->toBeTrue();
    });
});

describe('view', function () {
    it('allows any user to view equipment', function () {
        $member = User::factory()->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->view($member, $equipment))->toBeTrue();
    });

    it('allows guests to view equipment', function () {
        $equipment = Equipment::factory()->create();

        expect($this->policy->view(null, $equipment))->toBeTrue();
    });
});

describe('create', function () {
    it('allows equipment manager to create equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();

        expect($this->policy->create($manager))->toBeTrue();
    });

    it('denies regular members from creating equipment', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeFalse();
    });
});

describe('update', function () {
    it('allows equipment manager to update equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->update($manager, $equipment))->toBeTrue();
    });

    it('denies regular members from updating equipment', function () {
        $member = User::factory()->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->update($member, $equipment))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows equipment manager to delete equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->delete($manager, $equipment))->toBeTrue();
    });

    it('denies regular members from deleting equipment', function () {
        $member = User::factory()->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->delete($member, $equipment))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows equipment manager to restore equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->restore($manager, $equipment))->toBeTrue();
    });

    it('denies regular members from restoring equipment', function () {
        $member = User::factory()->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->restore($member, $equipment))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies equipment manager from force deleting equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->forceDelete($manager, $equipment))->toBeFalse();
    });

    it('denies admin from force deleting equipment', function () {
        $admin = User::factory()->withRole('admin')->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->forceDelete($admin, $equipment))->toBeFalse();
    });
});

describe('checkout', function () {
    it('allows equipment manager to checkout equipment', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->checkout($manager, $equipment))->toBeTrue();
    });

    it('denies regular members from checking out equipment via policy', function () {
        $member = User::factory()->create();
        $equipment = Equipment::factory()->create();

        expect($this->policy->checkout($member, $equipment))->toBeFalse();
    });
});

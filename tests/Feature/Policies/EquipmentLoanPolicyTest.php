<?php

use App\Models\User;
use App\Policies\EquipmentLoanPolicy;
use CorvMC\Equipment\Models\Equipment;
use CorvMC\Equipment\Models\EquipmentLoan;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new EquipmentLoanPolicy();
});

describe('manage', function () {
    it('allows equipment manager to manage loans', function () {
        $manager = User::factory()->withRole('equipment manager')->create();

        expect($this->policy->manage($manager))->toBeTrue();
    });

    it('denies regular members from managing loans', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies sustaining members from managing loans', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->manage($sustainingMember))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any authenticated user to view loans list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows equipment manager to view any loan', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->view($manager, $loan))->toBeTrue();
    });

    it('allows borrower to view their own loan', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->view($borrower, $loan))->toBeTrue();
    });

    it('denies non-borrower from viewing another users loan', function () {
        $member = User::factory()->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->view($member, $loan))->toBeFalse();
    });
});

describe('create', function () {
    it('allows equipment manager to create loans', function () {
        $manager = User::factory()->withRole('equipment manager')->create();

        expect($this->policy->create($manager))->toBeTrue();
    });

    it('denies regular members from creating loans', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeFalse();
    });
});

describe('update', function () {
    it('allows equipment manager to update any loan', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->update($manager, $loan))->toBeTrue();
    });

    it('denies borrower from updating their own loan', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->update($borrower, $loan))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows equipment manager to delete any loan', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->delete($manager, $loan))->toBeTrue();
    });

    it('denies borrower from deleting their own loan', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->delete($borrower, $loan))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows equipment manager to restore any loan', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->restore($manager, $loan))->toBeTrue();
    });

    it('denies borrower from restoring their own loan', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->restore($borrower, $loan))->toBeFalse();
    });
});

describe('forceDelete', function () {
    it('denies equipment manager from force deleting loans', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->forceDelete($manager, $loan))->toBeFalse();
    });

    it('denies admin from force deleting loans', function () {
        $admin = User::factory()->withRole('admin')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->forceDelete($admin, $loan))->toBeFalse();
    });
});

describe('cancel', function () {
    it('allows equipment manager to cancel any loan', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
            'checked_out_at' => now(),
        ]);

        expect($this->policy->cancel($manager, $loan))->toBeTrue();
    });

    it('allows borrower to cancel their loan if not checked out', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
            'checked_out_at' => null,
        ]);

        expect($this->policy->cancel($borrower, $loan))->toBeTrue();
    });

    it('denies borrower from cancelling their loan if already checked out', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
            'checked_out_at' => now(),
        ]);

        expect($this->policy->cancel($borrower, $loan))->toBeFalse();
    });

    it('denies non-borrower from cancelling another users loan', function () {
        $member = User::factory()->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
            'checked_out_at' => null,
        ]);

        expect($this->policy->cancel($member, $loan))->toBeFalse();
    });
});

describe('return', function () {
    it('allows equipment manager to process returns', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->return($manager, $loan))->toBeTrue();
    });

    it('denies borrower from processing their own return', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->return($borrower, $loan))->toBeFalse();
    });
});

describe('reportDamage', function () {
    it('allows equipment manager to report damage', function () {
        $manager = User::factory()->withRole('equipment manager')->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->reportDamage($manager, $loan))->toBeTrue();
    });

    it('allows borrower to report damage on their loan', function () {
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->reportDamage($borrower, $loan))->toBeTrue();
    });

    it('denies non-borrower from reporting damage on another users loan', function () {
        $member = User::factory()->create();
        $borrower = User::factory()->create();
        $equipment = Equipment::factory()->create();
        $loan = EquipmentLoan::factory()->create([
            'borrower_id' => $borrower->id,
            'equipment_id' => $equipment->id,
        ]);

        expect($this->policy->reportDamage($member, $loan))->toBeFalse();
    });
});

<?php

use App\Models\User;
use App\Policies\EventPolicy;
use CorvMC\Events\Models\Event;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new EventPolicy();
});

describe('manage', function () {
    it('allows production manager to manage events', function () {
        $manager = User::factory()->withRole('production manager')->create();

        expect($this->policy->manage($manager))->toBeTrue();
    });

    it('denies regular members from managing events', function () {
        $member = User::factory()->create();

        expect($this->policy->manage($member))->toBeFalse();
    });

    it('denies sustaining members from managing events', function () {
        $sustainingMember = User::factory()->sustainingMember()->create();

        expect($this->policy->manage($sustainingMember))->toBeFalse();
    });
});

describe('viewAny', function () {
    it('allows any authenticated user to view events list', function () {
        $member = User::factory()->create();

        expect($this->policy->viewAny($member))->toBeTrue();
    });
});

describe('view', function () {
    it('allows production manager to view any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'published_at' => null,
        ]);

        expect($this->policy->view($manager, $event))->toBeTrue();
    });

    it('allows organizer to view their own unpublished event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'published_at' => null,
        ]);

        expect($this->policy->view($organizer, $event))->toBeTrue();
    });

    it('allows any user to view published events', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'published_at' => now(),
        ]);

        expect($this->policy->view($member, $event))->toBeTrue();
    });

    it('denies non-organizer from viewing unpublished event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'published_at' => null,
        ]);

        expect($this->policy->view($member, $event))->toBeFalse();
    });
});

describe('create', function () {
    it('allows production manager to create events', function () {
        $manager = User::factory()->withRole('production manager')->create();

        expect($this->policy->create($manager))->toBeTrue();
    });

    it('denies regular members from creating events', function () {
        $member = User::factory()->create();

        expect($this->policy->create($member))->toBeFalse();
    });
});

describe('update', function () {
    it('allows production manager to update any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->update($manager, $event))->toBeTrue();
    });

    it('allows organizer to update their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->update($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from updating another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->update($member, $event))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows production manager to delete any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->delete($manager, $event))->toBeTrue();
    });

    it('allows organizer to delete their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->delete($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from deleting another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->delete($member, $event))->toBeFalse();
    });
});

describe('restore', function () {
    it('allows production manager to restore any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->restore($manager, $event))->toBeTrue();
    });

    it('allows organizer to restore their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->restore($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from restoring another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->restore($member, $event))->toBeFalse();
    });
});

describe('publish', function () {
    it('allows production manager to publish any event that can be published', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Concert Night',
        ]);

        expect($this->policy->publish($manager, $event))->toBeTrue();
    });

    it('allows organizer to publish their own event that can be published', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Concert Night',
        ]);

        expect($this->policy->publish($organizer, $event))->toBeTrue();
    });

    it('denies publishing event without title', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => null,
        ]);

        expect($this->policy->publish($organizer, $event))->toBeFalse();
    });

    it('denies non-organizer from publishing another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Concert Night',
        ]);

        expect($this->policy->publish($member, $event))->toBeFalse();
    });
});

describe('cancel', function () {
    it('allows production manager to cancel any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->cancel($manager, $event))->toBeTrue();
    });

    it('allows organizer to cancel their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->cancel($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from cancelling another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->cancel($member, $event))->toBeFalse();
    });
});

describe('postpone', function () {
    it('allows production manager to postpone any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->postpone($manager, $event))->toBeTrue();
    });

    it('allows organizer to postpone their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->postpone($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from postponing another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->postpone($member, $event))->toBeFalse();
    });
});

describe('reschedule', function () {
    it('allows production manager to reschedule any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->reschedule($manager, $event))->toBeTrue();
    });

    it('allows organizer to reschedule their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->reschedule($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from rescheduling another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->reschedule($member, $event))->toBeFalse();
    });
});

describe('managePerformers', function () {
    it('allows production manager to manage performers on any event', function () {
        $manager = User::factory()->withRole('production manager')->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->managePerformers($manager, $event))->toBeTrue();
    });

    it('allows organizer to manage performers on their own event', function () {
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->managePerformers($organizer, $event))->toBeTrue();
    });

    it('denies non-organizer from managing performers on another users event', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        expect($this->policy->managePerformers($member, $event))->toBeFalse();
    });
});

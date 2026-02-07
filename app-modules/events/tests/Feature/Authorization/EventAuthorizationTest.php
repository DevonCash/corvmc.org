<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use CorvMC\Moderation\Enums\Visibility;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create CMC venue for tests
    $this->cmcVenue = Venue::create([
        'name' => 'Corvallis Music Collective',
        'address' => '420 SW Washington Ave',
        'city' => 'Corvallis',
        'state' => 'OR',
        'zip' => '97333',
        'is_cmc' => true,
    ]);

    // Helper to create an event
    $this->createEvent = function (array $attributes = []) {
        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        return CreateEvent::run(array_merge([
            'title' => 'Test Event',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'visibility' => Visibility::Public,
        ], $attributes));
    };
});

describe('Event Visibility: Guest Access', function () {
    it('allows guests to view public published events', function () {
        $event = ($this->createEvent)(['visibility' => Visibility::Public]);
        $event->publish();

        expect(Gate::forUser(null)->allows('view', $event))->toBeTrue();
    });

    it('denies guests from viewing members-only published events', function () {
        $event = ($this->createEvent)(['visibility' => Visibility::Members]);
        $event->publish();

        expect(Gate::forUser(null)->allows('view', $event))->toBeFalse();
    });

    it('denies guests from viewing private published events', function () {
        $event = ($this->createEvent)(['visibility' => Visibility::Private]);
        $event->publish();

        expect(Gate::forUser(null)->allows('view', $event))->toBeFalse();
    });

    it('denies guests from viewing unpublished events', function () {
        $event = ($this->createEvent)(['visibility' => Visibility::Public]);
        // Event is not published

        expect(Gate::forUser(null)->allows('view', $event))->toBeFalse();
    });
});

describe('Event Visibility: Member Access', function () {
    it('allows members to view public published events', function () {
        $member = User::factory()->create();
        $event = ($this->createEvent)(['visibility' => Visibility::Public]);
        $event->publish();

        expect(Gate::forUser($member)->allows('view', $event))->toBeTrue();
    });

    it('allows members to view members-only published events', function () {
        $member = User::factory()->create();
        $event = ($this->createEvent)(['visibility' => Visibility::Members]);
        $event->publish();

        expect(Gate::forUser($member)->allows('view', $event))->toBeTrue();
    });

    it('denies regular members from viewing private events', function () {
        $member = User::factory()->create();
        $organizer = User::factory()->create();
        $event = ($this->createEvent)([
            'visibility' => Visibility::Private,
            'organizer_id' => $organizer->id,
        ]);
        $event->publish();

        expect(Gate::forUser($member)->allows('view', $event))->toBeFalse();
    });

    it('denies regular members from viewing unpublished events', function () {
        $member = User::factory()->create();
        $event = ($this->createEvent)(['visibility' => Visibility::Public]);
        // Event is not published

        expect(Gate::forUser($member)->allows('view', $event))->toBeFalse();
    });
});

describe('Event Visibility: Organizer Access', function () {
    it('allows organizer to view their own private events', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)([
            'visibility' => Visibility::Private,
            'organizer_id' => $organizer->id,
        ]);
        $event->publish();

        expect(Gate::forUser($organizer)->allows('view', $event))->toBeTrue();
    });

    it('allows organizer to view their own unpublished events', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)([
            'visibility' => Visibility::Public,
            'organizer_id' => $organizer->id,
        ]);
        // Event is not published

        expect(Gate::forUser($organizer)->allows('view', $event))->toBeTrue();
    });
});

describe('Event Visibility: Production Manager Access', function () {
    it('allows production managers to view all events regardless of visibility', function () {
        $manager = User::factory()->create();
        $manager->assignRole('production manager');

        // Create events at different times to avoid space reservation conflicts
        $baseTime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
        $publicEvent = ($this->createEvent)([
            'visibility' => Visibility::Public,
            'start_datetime' => $baseTime,
            'end_datetime' => $baseTime->copy()->addHours(3),
        ]);
        $membersEvent = ($this->createEvent)([
            'visibility' => Visibility::Members,
            'start_datetime' => $baseTime->copy()->addDays(1),
            'end_datetime' => $baseTime->copy()->addDays(1)->addHours(3),
        ]);
        $privateEvent = ($this->createEvent)([
            'visibility' => Visibility::Private,
            'start_datetime' => $baseTime->copy()->addDays(2),
            'end_datetime' => $baseTime->copy()->addDays(2)->addHours(3),
        ]);

        expect(Gate::forUser($manager)->allows('view', $publicEvent))->toBeTrue();
        expect(Gate::forUser($manager)->allows('view', $membersEvent))->toBeTrue();
        expect(Gate::forUser($manager)->allows('view', $privateEvent))->toBeTrue();
    });

    it('allows production managers to view unpublished events', function () {
        $manager = User::factory()->create();
        $manager->assignRole('production manager');

        $event = ($this->createEvent)(['visibility' => Visibility::Public]);
        // Event is not published

        expect(Gate::forUser($manager)->allows('view', $event))->toBeTrue();
    });
});

describe('Event Authorization: Create', function () {
    it('denies regular users from creating events', function () {
        $user = User::factory()->create();

        expect(Gate::forUser($user)->allows('create', Event::class))->toBeFalse();
    });

    it('allows production managers to create events', function () {
        $manager = User::factory()->create();
        $manager->assignRole('production manager');

        expect(Gate::forUser($manager)->allows('create', Event::class))->toBeTrue();
    });
});

describe('Event Authorization: Update', function () {
    it('allows organizer to update their event', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($organizer)->allows('update', $event))->toBeTrue();
    });

    it('denies other users from updating events', function () {
        $organizer = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($otherUser)->allows('update', $event))->toBeFalse();
    });

    it('allows production managers to update any event', function () {
        $organizer = User::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole('production manager');
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($manager)->allows('update', $event))->toBeTrue();
    });
});

describe('Event Authorization: Delete', function () {
    it('allows organizer to delete their event', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($organizer)->allows('delete', $event))->toBeTrue();
    });

    it('denies other users from deleting events', function () {
        $organizer = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($otherUser)->allows('delete', $event))->toBeFalse();
    });

    it('allows production managers to delete any event', function () {
        $organizer = User::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole('production manager');
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($manager)->allows('delete', $event))->toBeTrue();
    });
});

describe('Event Authorization: Publish', function () {
    it('allows organizer to publish their event', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($organizer)->allows('publish', $event))->toBeTrue();
    });

    it('denies publishing events that cannot be published', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        // Clear the title to make the event unpublishable
        $event->update(['title' => '']);

        expect($event->canPublish())->toBeFalse();
        expect(Gate::forUser($organizer)->allows('publish', $event))->toBeFalse();
    });
});

describe('Event Authorization: Cancel', function () {
    it('allows organizer to cancel their event', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($organizer)->allows('cancel', $event))->toBeTrue();
    });

    it('denies other users from cancelling events', function () {
        $organizer = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($otherUser)->allows('cancel', $event))->toBeFalse();
    });

    it('allows production managers to cancel any event', function () {
        $organizer = User::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole('production manager');
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($manager)->allows('cancel', $event))->toBeTrue();
    });
});

describe('Event Authorization: Reschedule', function () {
    it('allows organizer to reschedule their event', function () {
        $organizer = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($organizer)->allows('reschedule', $event))->toBeTrue();
    });

    it('denies other users from rescheduling events', function () {
        $organizer = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($otherUser)->allows('reschedule', $event))->toBeFalse();
    });

    it('allows production managers to reschedule any event', function () {
        $organizer = User::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole('production manager');
        $event = ($this->createEvent)(['organizer_id' => $organizer->id]);

        expect(Gate::forUser($manager)->allows('reschedule', $event))->toBeTrue();
    });
});

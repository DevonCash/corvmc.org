<?php

use CorvMC\Bands\Models\Band;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Events\Actions\AddEventPerformer;
use CorvMC\Events\Actions\CancelEvent;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Actions\DeleteEvent;
use CorvMC\Events\Actions\DuplicateEvent;
use CorvMC\Events\Actions\PublishEvent;
use CorvMC\Events\Actions\RemoveEventPerformer;
use CorvMC\Events\Actions\RescheduleEvent;
use CorvMC\Events\Actions\UpdateEvent;
use CorvMC\Events\Actions\UpdatePerformerOrder;
use CorvMC\Events\Actions\UpdatePerformerSetLength;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use Illuminate\Support\Facades\Auth;
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

    // Create external venue for tests
    $this->externalVenue = Venue::create([
        'name' => 'The Beanery',
        'address' => '500 SW 2nd St',
        'city' => 'Corvallis',
        'state' => 'OR',
        'zip' => '97333',
        'is_cmc' => false,
    ]);
});

describe('Event Workflow: Create Event', function () {
    it('creates an event at CMC venue', function () {
        $organizer = User::factory()->create();
        $organizer->assignRole('production manager');

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
        $endDatetime = $startDatetime->copy()->addHours(3);

        $event = CreateEvent::run([
            'title' => 'Rock Night',
            'description' => 'An evening of rock music',
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $organizer->id,
        ]);

        expect($event)->toBeInstanceOf(Event::class);
        expect($event->title)->toBe('Rock Night');
        expect($event->venue_id)->toBe($this->cmcVenue->id);
        expect($event->organizer_id)->toBe($organizer->id);
        expect($event->status)->toBe(EventStatus::Scheduled);
    });

    it('creates an event at external venue', function () {
        $organizer = User::factory()->create();

        $startDatetime = Carbon::now()->addDays(7)->setHour(20)->setMinute(0)->setSecond(0);
        $endDatetime = $startDatetime->copy()->addHours(2);

        $event = CreateEvent::run([
            'title' => 'Jazz at the Beanery',
            'description' => 'Smooth jazz evening',
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'venue_id' => $this->externalVenue->id,
            'organizer_id' => $organizer->id,
        ]);

        expect($event->venue_id)->toBe($this->externalVenue->id);
        expect($event->isExternalVenue())->toBeTrue();
    });

    it('attaches tags when creating event', function () {
        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Multi-Genre Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'tags' => ['Rock', 'Jazz', 'Blues'],
        ]);

        expect($event->tags->count())->toBe(3);
        expect($event->tags->pluck('name')->toArray())->toContain('Rock', 'Jazz', 'Blues');
    });
});

describe('Event Workflow: Publish Event', function () {
    it('publishes an event and sets published_at timestamp', function () {
        $organizer = User::factory()->create();
        $organizer->assignRole('production manager');
        Auth::setUser($organizer);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Unpublished Event',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(2),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $organizer->id,
        ]);

        expect($event->published_at)->toBeNull();

        PublishEvent::run($event);

        $event->refresh();
        expect($event->published_at)->not->toBeNull();
        expect($event->isPublished())->toBeTrue();
    });
});

describe('Event Workflow: Manage Performers', function () {
    it('adds performers to an event', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        $result = AddEventPerformer::run($event, $band, ['set_length' => 45]);

        expect($result)->toBeTrue();
        expect($event->performers()->count())->toBe(1);
        expect($event->hasPerformer($band))->toBeTrue();

        // Check pivot data
        $pivot = $event->performers()->first()->pivot;
        expect($pivot->set_length)->toBe(45);
        expect($pivot->order)->toBe(1);
    });

    it('auto-assigns order when adding multiple performers', function () {
        $owner = User::factory()->create();
        $band1 = Band::factory()->create(['owner_id' => $owner->id]);
        $band2 = Band::factory()->create(['owner_id' => $owner->id]);
        $band3 = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Multi-Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(4),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $band1);
        AddEventPerformer::run($event, $band2);
        AddEventPerformer::run($event, $band3);

        $performers = $event->performers()->orderBy('event_bands.order')->get();
        expect($performers[0]->id)->toBe($band1->id);
        expect($performers[0]->pivot->order)->toBe(1);
        expect($performers[1]->pivot->order)->toBe(2);
        expect($performers[2]->pivot->order)->toBe(3);
    });

    it('removes a performer from an event', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $band);
        expect($event->performers()->count())->toBe(1);

        $result = RemoveEventPerformer::run($event, $band);

        expect($result)->toBeTrue();
        expect($event->performers()->count())->toBe(0);
        expect($event->hasPerformer($band))->toBeFalse();
    });

    it('returns false when adding duplicate performer', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        $firstAdd = AddEventPerformer::run($event, $band);
        $secondAdd = AddEventPerformer::run($event, $band);

        expect($firstAdd)->toBeTrue();
        expect($secondAdd)->toBeFalse();
        expect($event->performers()->count())->toBe(1);
    });
});

describe('Event Workflow: Reschedule Event', function () {
    it('reschedules an event to a new date', function () {
        $organizer = User::factory()->create();

        $originalStartDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
        $newStartDatetime = Carbon::now()->addDays(14)->setHour(19)->setMinute(0)->setSecond(0);

        $originalEvent = CreateEvent::run([
            'title' => 'Original Event',
            'description' => 'Original description',
            'start_datetime' => $originalStartDatetime,
            'end_datetime' => $originalStartDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $organizer->id,
        ]);

        $newEvent = RescheduleEvent::run($originalEvent, [
            'start_datetime' => $newStartDatetime,
            'end_datetime' => $newStartDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ], 'Venue conflict');

        $originalEvent->refresh();

        // Original event should be marked as postponed
        expect($originalEvent->status)->toBe(EventStatus::Postponed);
        expect($originalEvent->rescheduled_to_id)->toBe($newEvent->id);
        expect($originalEvent->reschedule_reason)->toBe('Venue conflict');

        // New event should have the updated time
        expect($newEvent->title)->toBe('Original Event');
        expect($newEvent->description)->toBe('Original description');
        expect($newEvent->start_datetime->format('Y-m-d'))->toBe($newStartDatetime->format('Y-m-d'));
    });

    it('copies performers when rescheduling', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $originalStartDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
        $newStartDatetime = Carbon::now()->addDays(14)->setHour(19)->setMinute(0)->setSecond(0);

        $originalEvent = CreateEvent::run([
            'title' => 'Original Event',
            'start_datetime' => $originalStartDatetime,
            'end_datetime' => $originalStartDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($originalEvent, $band, ['set_length' => 45, 'order' => 1]);

        $newEvent = RescheduleEvent::run($originalEvent, [
            'start_datetime' => $newStartDatetime,
            'end_datetime' => $newStartDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        expect($newEvent->performers()->count())->toBe(1);
        expect($newEvent->hasPerformer($band))->toBeTrue();
        expect($newEvent->performers()->first()->pivot->set_length)->toBe(45);
    });
});

describe('Event Workflow: Cancel Event', function () {
    it('cancels an event with a reason', function () {
        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Event to Cancel',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        expect($event->status)->toBe(EventStatus::Scheduled);

        $cancelledEvent = CancelEvent::run($event, 'Weather emergency');

        expect($cancelledEvent->status)->toBe(EventStatus::Cancelled);
        expect($cancelledEvent->cancellation_reason)->toBe('Weather emergency');
    });

    it('cancels an event without a reason', function () {
        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Event to Cancel',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        $cancelledEvent = CancelEvent::run($event);

        expect($cancelledEvent->status)->toBe(EventStatus::Cancelled);
        expect($cancelledEvent->cancellation_reason)->toBeNull();
    });
});

describe('Event Workflow: Update Event', function () {
    it('updates event title, description, datetime, venue', function () {
        $organizer = User::factory()->create();
        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Original Title',
            'description' => 'Original description',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'organizer_id' => $organizer->id,
        ]);

        $newStartDatetime = Carbon::now()->addDays(14)->setHour(20)->setMinute(0)->setSecond(0);

        $updatedEvent = UpdateEvent::run($event, [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'start_datetime' => $newStartDatetime,
            'end_datetime' => $newStartDatetime->copy()->addHours(4),
            'venue_id' => $this->externalVenue->id,
        ]);

        expect($updatedEvent->title)->toBe('Updated Title');
        expect($updatedEvent->description)->toBe('Updated description');
        expect($updatedEvent->start_datetime->format('Y-m-d'))->toBe($newStartDatetime->format('Y-m-d'));
        expect($updatedEvent->venue_id)->toBe($this->externalVenue->id);
    });

    it('updates event tags', function () {
        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Tagged Event',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
            'tags' => ['Rock', 'Blues'],
        ]);

        expect($event->tags->count())->toBe(2);

        $updatedEvent = UpdateEvent::run($event, [
            'tags' => ['Jazz', 'Folk', 'Indie'],
        ]);

        expect($updatedEvent->tags->count())->toBe(3);
        expect($updatedEvent->tags->pluck('name')->toArray())->toContain('Jazz', 'Folk', 'Indie');
        expect($updatedEvent->tags->pluck('name')->toArray())->not->toContain('Rock', 'Blues');
    });
});

describe('Event Workflow: Delete Event', function () {
    it('deletes an event and removes performers', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Event to Delete',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $band);
        expect($event->performers()->count())->toBe(1);

        $eventId = $event->id;

        DeleteEvent::run($event);

        // Event should be deleted
        expect(Event::find($eventId))->toBeNull();
    });
});

describe('Event Workflow: Duplicate Event', function () {
    it('duplicates event with new date, copies performers and tags', function () {
        $owner = User::factory()->create();
        $band1 = Band::factory()->create(['owner_id' => $owner->id]);
        $band2 = Band::factory()->create(['owner_id' => $owner->id]);

        $originalStartDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);
        $newStartDatetime = Carbon::now()->addDays(30)->setHour(20)->setMinute(0)->setSecond(0);

        $originalEvent = CreateEvent::run([
            'title' => 'Original Concert',
            'description' => 'An amazing concert',
            'start_datetime' => $originalStartDatetime,
            'end_datetime' => $originalStartDatetime->copy()->addHours(4),
            'venue_id' => $this->cmcVenue->id,
            'tags' => ['Rock', 'Alternative'],
        ]);

        AddEventPerformer::run($originalEvent, $band1, ['set_length' => 45, 'order' => 1]);
        AddEventPerformer::run($originalEvent, $band2, ['set_length' => 60, 'order' => 2]);

        $duplicatedEvent = DuplicateEvent::run(
            $originalEvent,
            $newStartDatetime,
            $newStartDatetime->copy()->addHours(4)
        );

        // Check basic event details are copied
        expect($duplicatedEvent->title)->toBe('Original Concert');
        expect($duplicatedEvent->description)->toBe('An amazing concert');
        expect($duplicatedEvent->venue_id)->toBe($this->cmcVenue->id);

        // Check new date is set
        expect($duplicatedEvent->start_datetime->format('Y-m-d'))->toBe($newStartDatetime->format('Y-m-d'));

        // Check status is reset
        expect($duplicatedEvent->published_at)->toBeNull();

        // Check performers are copied with pivot data
        expect($duplicatedEvent->performers()->count())->toBe(2);
        $performers = $duplicatedEvent->performers()->orderBy('event_bands.order')->get();
        expect($performers[0]->id)->toBe($band1->id);
        expect($performers[0]->pivot->set_length)->toBe(45);
        expect($performers[0]->pivot->order)->toBe(1);
        expect($performers[1]->id)->toBe($band2->id);
        expect($performers[1]->pivot->set_length)->toBe(60);

        // Check tags are copied
        expect($duplicatedEvent->tags->count())->toBe(2);
        expect($duplicatedEvent->tags->pluck('name')->toArray())->toContain('Rock', 'Alternative');

        // Original event should be unchanged
        expect($originalEvent->fresh()->start_datetime->format('Y-m-d'))->toBe($originalStartDatetime->format('Y-m-d'));
    });
});

describe('Event Workflow: Performer Management Extended', function () {
    it('updates performer order', function () {
        $owner = User::factory()->create();
        $band1 = Band::factory()->create(['owner_id' => $owner->id]);
        $band2 = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Multi-Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(4),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $band1);
        AddEventPerformer::run($event, $band2);

        // Band1 should be order 1, band2 should be order 2
        expect($event->performers()->where('band_profile_id', $band1->id)->first()->pivot->order)->toBe(1);
        expect($event->performers()->where('band_profile_id', $band2->id)->first()->pivot->order)->toBe(2);

        // Update band2 to be first
        $result = UpdatePerformerOrder::run($event, $band2, 1);
        expect($result)->toBeTrue();

        // Verify order was updated
        expect($event->performers()->where('band_profile_id', $band2->id)->first()->pivot->order)->toBe(1);
    });

    it('returns false when updating order for non-performer', function () {
        $owner = User::factory()->create();
        $bandOnEvent = Band::factory()->create(['owner_id' => $owner->id]);
        $bandNotOnEvent = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $bandOnEvent);

        $result = UpdatePerformerOrder::run($event, $bandNotOnEvent, 1);
        expect($result)->toBeFalse();
    });

    it('updates performer set length', function () {
        $owner = User::factory()->create();
        $band = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $band, ['set_length' => 30]);
        expect($event->performers()->first()->pivot->set_length)->toBe(30);

        $result = UpdatePerformerSetLength::run($event, $band, 60);
        expect($result)->toBeTrue();

        // Verify set length was updated
        expect($event->performers()->first()->pivot->set_length)->toBe(60);
    });

    it('returns false when updating set length for non-performer', function () {
        $owner = User::factory()->create();
        $bandOnEvent = Band::factory()->create(['owner_id' => $owner->id]);
        $bandNotOnEvent = Band::factory()->create(['owner_id' => $owner->id]);

        $startDatetime = Carbon::now()->addDays(7)->setHour(19)->setMinute(0)->setSecond(0);

        $event = CreateEvent::run([
            'title' => 'Band Night',
            'start_datetime' => $startDatetime,
            'end_datetime' => $startDatetime->copy()->addHours(3),
            'venue_id' => $this->cmcVenue->id,
        ]);

        AddEventPerformer::run($event, $bandOnEvent);

        $result = UpdatePerformerSetLength::run($event, $bandNotOnEvent, 45);
        expect($result)->toBeFalse();
    });
});

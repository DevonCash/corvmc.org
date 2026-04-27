<?php

use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\ShiftService;

beforeEach(function () {
    $this->service = app(ShiftService::class);
    $this->position = Position::factory()->create();
});

describe('ShiftService::create', function () {
    it('creates a shift with valid data', function () {
        $shift = $this->service->create([
            'position_id' => $this->position->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 2,
        ]);

        expect($shift)->toBeInstanceOf(Shift::class);
        expect($shift->position_id)->toBe($this->position->id);
        expect($shift->capacity)->toBe(2);
        expect($shift->event_id)->toBeNull();
    });

    it('creates a shift with an event_id', function () {
        $shift = $this->service->create([
            'position_id' => $this->position->id,
            'event_id' => 42,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 1,
        ]);

        expect($shift->event_id)->toBe(42);
    });

    it('rejects a soft-deleted position', function () {
        $this->position->delete();

        $this->service->create([
            'position_id' => $this->position->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 1,
        ]);
    })->throws(InvalidArgumentException::class, 'Position does not exist');

    it('rejects start_at >= end_at', function () {
        $this->service->create([
            'position_id' => $this->position->id,
            'start_at' => now()->addDay()->addHours(3),
            'end_at' => now()->addDay(),
            'capacity' => 1,
        ]);
    })->throws(InvalidArgumentException::class, 'start_at must be before end_at');

    it('rejects capacity less than 1', function () {
        $this->service->create([
            'position_id' => $this->position->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
            'capacity' => 0,
        ]);
    })->throws(InvalidArgumentException::class, 'Capacity must be at least 1');
});

describe('ShiftService::update', function () {
    it('updates shift fields', function () {
        $shift = Shift::factory()->for($this->position, 'position')->create();

        $updated = $this->service->update($shift, ['capacity' => 5]);

        expect($updated->capacity)->toBe(5);
    });

    it('rejects invalid time range on update', function () {
        $shift = Shift::factory()->for($this->position, 'position')->create();

        $this->service->update($shift, [
            'start_at' => now()->addDay()->addHours(5),
            'end_at' => now()->addDay(),
        ]);
    })->throws(InvalidArgumentException::class, 'start_at must be before end_at');
});

describe('ShiftService::delete', function () {
    it('deletes a shift', function () {
        $shift = Shift::factory()->for($this->position, 'position')->create();

        $this->service->delete($shift);

        expect(Shift::find($shift->id))->toBeNull();
    });
});

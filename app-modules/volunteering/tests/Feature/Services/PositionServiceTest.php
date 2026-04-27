<?php

use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Services\PositionService;

beforeEach(function () {
    $this->service = app(PositionService::class);
});

describe('PositionService', function () {
    it('creates a position with title and description', function () {
        $position = $this->service->create([
            'title' => 'Sound Person',
            'description' => 'Runs the sound board during shows.',
        ]);

        expect($position)->toBeInstanceOf(Position::class);
        expect($position->title)->toBe('Sound Person');
        expect($position->description)->toBe('Runs the sound board during shows.');
        expect($position->exists)->toBeTrue();
    });

    it('creates a position without description', function () {
        $position = $this->service->create([
            'title' => 'Door Volunteer',
        ]);

        expect($position->title)->toBe('Door Volunteer');
        expect($position->description)->toBeNull();
    });

    it('updates a position title', function () {
        $position = Position::factory()->create(['title' => 'Old Title']);

        $updated = $this->service->update($position, ['title' => 'New Title']);

        expect($updated->title)->toBe('New Title');
    });

    it('updates a position description to null', function () {
        $position = Position::factory()->create(['description' => 'Some text']);

        $updated = $this->service->update($position, ['description' => null]);

        expect($updated->description)->toBeNull();
    });

    it('soft-deletes a position', function () {
        $position = Position::factory()->create();

        $this->service->delete($position);

        expect(Position::find($position->id))->toBeNull();
        expect(Position::withTrashed()->find($position->id))->not->toBeNull();
    });

    it('soft-deleted positions are excluded from default queries', function () {
        $kept = Position::factory()->create();
        $deleted = Position::factory()->create();

        $this->service->delete($deleted);

        expect(Position::pluck('id')->all())->toBe([$kept->id]);
    });
});

<?php

namespace CorvMC\Volunteering\Services;

use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShiftService
{
    public function create(array $data): Shift
    {
        $this->validate($data);

        return DB::transaction(function () use ($data) {
            return Shift::create([
                'position_id' => $data['position_id'],
                'event_id' => $data['event_id'] ?? null,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'capacity' => $data['capacity'],
            ]);
        });
    }

    public function update(Shift $shift, array $data): Shift
    {
        if (isset($data['start_at']) || isset($data['end_at'])) {
            $startAt = $data['start_at'] ?? $shift->start_at;
            $endAt = $data['end_at'] ?? $shift->end_at;

            if ($startAt >= $endAt) {
                throw new InvalidArgumentException('start_at must be before end_at.');
            }
        }

        if (isset($data['capacity']) && $data['capacity'] < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1.');
        }

        if (isset($data['position_id'])) {
            $position = Position::find($data['position_id']);
            if (! $position || $position->trashed()) {
                throw new InvalidArgumentException('Position does not exist or has been deleted.');
            }
        }

        $shift->update(array_intersect_key($data, array_flip([
            'position_id', 'event_id', 'start_at', 'end_at', 'capacity',
        ])));

        return $shift->fresh();
    }

    public function delete(Shift $shift): void
    {
        $shift->delete();
    }

    private function validate(array $data): void
    {
        if (! isset($data['position_id'])) {
            throw new InvalidArgumentException('position_id is required.');
        }

        $position = Position::find($data['position_id']);
        if (! $position || $position->trashed()) {
            throw new InvalidArgumentException('Position does not exist or has been deleted.');
        }

        if (! isset($data['start_at']) || ! isset($data['end_at'])) {
            throw new InvalidArgumentException('start_at and end_at are required.');
        }

        if ($data['start_at'] >= $data['end_at']) {
            throw new InvalidArgumentException('start_at must be before end_at.');
        }

        if (! isset($data['capacity']) || $data['capacity'] < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1.');
        }
    }
}

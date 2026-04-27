<?php

namespace CorvMC\Volunteering\Services;

use CorvMC\Volunteering\Models\Position;

class PositionService
{
    public function create(array $data): Position
    {
        return Position::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(Position $position, array $data): Position
    {
        $position->update(array_intersect_key($data, array_flip(['title', 'description'])));

        return $position->fresh();
    }

    public function delete(Position $position): void
    {
        $position->delete();
    }
}

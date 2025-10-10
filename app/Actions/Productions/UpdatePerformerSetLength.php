<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePerformerSetLength
{
    use AsAction;

    /**
     * Update a performer's set length.
     */
    public function handle(Production $production, Band $band, ?int $setLength): bool
    {
        if (!HasPerformer::run($production, $band)) {
            return false;
        }

        $production->performers()->updateExistingPivot($band->id, [
            'set_length' => $setLength,
        ]);

        return true;
    }
}

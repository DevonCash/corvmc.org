<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdatePerformerOrder
{
    use AsAction;

    /**
     * Update a performer's order in the lineup.
     */
    public function handle(Production $production, Band $band, int $order): bool
    {
        if (!HasPerformer::run($production, $band)) {
            return false;
        }

        $production->performers()->updateExistingPivot($band->id, [
            'order' => $order,
        ]);

        return true;
    }
}

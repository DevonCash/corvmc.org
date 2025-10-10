<?php

namespace App\Actions\Productions;

use App\Models\Band;
use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class RemovePerformer
{
    use AsAction;

    /**
     * Remove a performer from a production.
     */
    public function handle(Production $production, Band $band): bool
    {
        return $production->performers()->detach($band->id) > 0;
    }
}

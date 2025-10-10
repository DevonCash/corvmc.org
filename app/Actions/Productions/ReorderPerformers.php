<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReorderPerformers
{
    use AsAction;

    /**
     * Reorder all performers in a production.
     */
    public function handle(Production $production, array $bandIds): bool
    {
        DB::transaction(function () use ($production, $bandIds) {
            foreach ($bandIds as $index => $bandId) {
                $production->performers()->updateExistingPivot($bandId, [
                    'order' => $index + 1,
                ]);
            }
        });

        return true;
    }
}

<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkAsCompleted
{
    use AsAction;

    /**
     * Mark a production as completed.
     */
    public function handle(Production $production): bool
    {
        $production->update([
            'status' => 'completed',
        ]);

        return true;
    }
}

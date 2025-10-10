<?php

namespace App\Actions\Productions;

use App\Models\Production;
use Lorisleiva\Actions\Concerns\AsAction;

class UnpublishProduction
{
    use AsAction;

    /**
     * Unpublish a production.
     */
    public function handle(Production $production): bool
    {
        if (!$production->isPublished()) {
            return false;
        }

        $production->update([
            'status' => 'in-production',
            'published_at' => null,
        ]);

        return true;
    }
}

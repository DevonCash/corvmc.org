<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use Lorisleiva\Actions\Concerns\AsAction;

class ApplyRevision
{
    use AsAction;

    /**
     * Apply an approved revision to its model.
     */
    public function handle(Revision $revision): bool
    {
        $model = $revision->revisionable;

        if (!$model) {
            throw new \InvalidArgumentException('Revisionable model not found');
        }

        // Use forceUpdate to bypass revision system for the actual update
        return $model->forceUpdate($revision->proposed_changes);
    }
}

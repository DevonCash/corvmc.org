<?php

namespace CorvMC\Moderation\Actions\Revisions;

use CorvMC\Moderation\Models\Revision;
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

        if (! $model) {
            throw new \InvalidArgumentException('Revisionable model not found');
        }

        // Ensure the model uses the Revisionable trait
        if (! method_exists($model, 'forceUpdate')) {
            throw new \InvalidArgumentException('Model does not support revisions');
        }

        // Use forceUpdate to bypass revision system for the actual update
        return $model->forceUpdate($revision->proposed_changes);
    }
}

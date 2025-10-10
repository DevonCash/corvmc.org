<?php

namespace App\Actions\Revisions;

use App\Models\Revision;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPendingRevisionsSummary
{
    use AsAction;

    /**
     * Get pending revisions summary.
     */
    public function handle(): array
    {
        $pending = Revision::pending();

        return [
            'total' => $pending->count(),
            'by_type' => $pending->selectRaw('revisionable_type, COUNT(*) as count')
                ->groupBy('revisionable_type')
                ->pluck('count', 'revisionable_type')
                ->toArray(),
            'by_priority' => $this->getPendingByPriority(),
            'oldest' => $pending->oldest()->first(),
        ];
    }

    /**
     * Get pending revisions grouped by priority.
     */
    protected function getPendingByPriority(): array
    {
        $pending = Revision::pending()->with('submittedBy')->get();
        $priority = ['urgent' => 0, 'fast-track' => 0, 'standard' => 0];

        foreach ($pending as $revision) {
            $submitter = $revision->submittedBy;
            $model = $revision->revisionable;
            $contentType = $model ? get_class($model) : null;

            if ($contentType) {
                $workflow = \App\Actions\Trust\DetermineApprovalWorkflow::run($submitter, $contentType);
                $priority[$workflow['review_priority']] = ($priority[$workflow['review_priority']] ?? 0) + 1;
            }
        }

        return $priority;
    }
}

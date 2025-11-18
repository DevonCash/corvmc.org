<?php

namespace App\Actions\Trust;

use App\Contracts\Reportable;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleContentViolation
{
    use AsAction;

    /**
     * Handle content violation and adjust trust points accordingly.
     */
    public function handle(User $user, Reportable $content, string $violationType, string $contentType = 'global'): void
    {
        $contentId = $content->getKey();
        $contentTitle = $content->title ?? $content->name ?? class_basename($content);

        $reason = 'Content violation for '.$contentTitle;

        PenalizeViolation::run($user, $violationType, $contentType, $contentId, $reason);

        Log::warning('Content violation handled', [
            'user_id' => $user->id,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'violation_type' => $violationType,
            'new_trust_points' => $user->getTrustBalance($contentType),
        ]);
    }
}

<?php

namespace App\Actions\Trust;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleContentViolation
{
    use AsAction;

    /**
     * Handle content violation and adjust trust points accordingly.
     */
    public function handle(User $user, Model $content, string $violationType, string $contentType = 'global'): void
    {
        $reason = 'Content violation for '.($content->title ?? $content->name ?? class_basename($content));

        PenalizeViolation::run($user, $violationType, $contentType, $content->id, $reason);

        Log::warning('Content violation handled', [
            'user_id' => $user->id,
            'content_type' => $contentType,
            'content_id' => $content->id,
            'violation_type' => $violationType,
            'new_trust_points' => $user->getTrustBalance($contentType),
        ]);
    }
}

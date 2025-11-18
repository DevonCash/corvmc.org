<?php

namespace App\Actions\Trust;

use App\Contracts\Reportable;
use App\Models\User;
use App\Support\TrustConstants;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AwardSuccessfulContent
{
    use AsAction;

    /**
     * Award points for successful content.
     */
    public function handle(User $user, Reportable $content, ?string $contentType = null, bool $forceAward = false): void
    {
        $contentType = $contentType ?? get_class($content);

        // Only award if content should be evaluated
        if (! $forceAward && ! $this->shouldEvaluateContent($content)) {
            return;
        }

        // Check for upheld reports
        $hasUpheldReports = $content->reports()
            ->where('status', 'upheld')
            ->exists();

        if (! $hasUpheldReports) {
            AwardTrustPoints::run(
                $user,
                TrustConstants::POINTS_SUCCESSFUL_CONTENT,
                $contentType,
                'successful_content',
                $content->id,
                'Successful content: '.($content->title ?? $content->name ?? $content->id)
            );

            Log::info('Trust points awarded for successful content', [
                'user_id' => $user->id,
                'content_type' => $contentType,
                'content_id' => $content->id,
                'points_awarded' => TrustConstants::POINTS_SUCCESSFUL_CONTENT,
                'new_total' => $user->getTrustBalance($contentType),
            ]);
        }
    }

    /**
     * Determine if content should be evaluated for trust.
     */
    protected function shouldEvaluateContent(Reportable $content): bool
    {
        if ($content instanceof \App\Models\Event) {
            return $content->status === 'completed';
        }

        if ($content instanceof \App\Models\MemberProfile || $content instanceof \App\Models\Band) {
            return true;
        }

        return true;
    }
}

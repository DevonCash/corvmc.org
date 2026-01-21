<?php

namespace CorvMC\Moderation\Actions\Trust;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

use App\Models\User;

use CorvMC\Moderation\Contracts\Reportable;

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
            $contentId = $content->getKey();
            $contentTitle = $content->title ?? $content->name ?? $contentId;

            AwardTrustPoints::run(
                $user,
                config('moderation.points.successful_content', 1),
                $contentType,
                'successful_content',
                $contentId,
                'Successful content: ' . $contentTitle
            );

            Log::info('Trust points awarded for successful content', [
                'user_id' => $user->id,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'points_awarded' => config('moderation.points.successful_content', 1),
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

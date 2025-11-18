<?php

namespace App\Actions\Trust;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrustBadge
{
    use AsAction;

    /**
     * Get trust badge information for display.
     */
    public function handle(User $user, string $contentType = 'global'): ?array
    {
        $level = GetTrustLevel::run($user, $contentType);
        $typeName = $this->getContentTypeName($contentType);

        return match ($level) {
            'auto-approved' => [
                'label' => "Auto-Approved {$typeName}",
                'color' => 'success',
                'icon' => 'tabler-shield-check',
                'description' => 'Content from this user is automatically approved',
            ],
            'verified' => [
                'label' => "Verified {$typeName}",
                'color' => 'info',
                'icon' => 'tabler-shield',
                'description' => 'Trusted community member',
            ],
            'trusted' => [
                'label' => "Trusted {$typeName}",
                'color' => 'warning',
                'icon' => 'tabler-star',
                'description' => 'Reliable community member',
            ],
            default => null
        };
    }

    /**
     * Get human-readable content type name.
     */
    protected function getContentTypeName(string $contentType): string
    {
        return match ($contentType) {
            'App\\Models\\Event' => 'Event Organizer',
            'App\\Models\\MemberProfile' => 'Profile Creator',
            'App\\Models\\Band' => 'Band Manager',
            'global' => 'Community Member',
            default => 'Content Creator'
        };
    }
}

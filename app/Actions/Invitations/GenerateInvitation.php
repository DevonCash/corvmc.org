<?php

namespace App\Actions\Invitations;

use App\Services\InvitationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use InvitationService::generate() instead
 * This action is maintained for backward compatibility only.
 * New code should use the InvitationService directly.
 */
class GenerateInvitation
{
    use AsAction;

    /**
     * @deprecated Use InvitationService::generate() instead
     */
    public function handle(...$args)
    {
        return app(InvitationService::class)->generate(...$args);
    }
}

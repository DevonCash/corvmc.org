<?php

namespace CorvMC\Support\Events;

use CorvMC\Support\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvitationAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
    ) {}
}

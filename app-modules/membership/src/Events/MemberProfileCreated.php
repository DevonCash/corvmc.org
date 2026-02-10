<?php

namespace CorvMC\Membership\Events;

use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberProfileCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public MemberProfile $profile,
    ) {}
}

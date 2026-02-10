<?php

namespace CorvMC\Membership\Events;

use CorvMC\Membership\Models\MemberProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberProfileUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public MemberProfile $profile,
        public array $changedFields,
        public array $oldValues,
    ) {}
}

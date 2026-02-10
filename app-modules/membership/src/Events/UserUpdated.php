<?php

namespace CorvMC\Membership\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public array $changedFields,
        public array $oldValues,
    ) {}
}

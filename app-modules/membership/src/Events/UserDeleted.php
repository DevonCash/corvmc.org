<?php

namespace CorvMC\Membership\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}
}

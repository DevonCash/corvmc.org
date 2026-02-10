<?php

namespace CorvMC\Moderation\Events;

use App\Models\User;
use CorvMC\Moderation\Models\Revision;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RevisionRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Revision $revision,
        public User $reviewer,
        public string $reason,
    ) {}
}

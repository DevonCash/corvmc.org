<?php

namespace CorvMC\Moderation\Events;

use CorvMC\Moderation\Models\Revision;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RevisionAutoApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Revision $revision,
    ) {}
}

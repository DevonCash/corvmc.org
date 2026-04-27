<?php

namespace CorvMC\Moderation\Enums;

enum RevisionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

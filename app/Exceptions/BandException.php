<?php

namespace App\Exceptions;

use Exception;

class BandException extends Exception
{
    public static function cannotRemoveOwner(): self
    {
        return new self('Cannot remove the band owner. Transfer ownership first.');
    }

    public static function cannotChangeOwnerRole(): self
    {
        return new self('Cannot change the owner\'s role. Transfer ownership first.');
    }

    public static function bandAlreadyHasOwner(): self
    {
        return new self('This band already has an owner and cannot be claimed.');
    }

    public static function userAlreadyMember(): self
    {
        return new self('User is already a member of this band.');
    }

    public static function userAlreadyInvited(): self
    {
        return new self('User has already been invited to this band.');
    }

    public static function userNotMember(): self
    {
        return new self('User is not a member of this band.');
    }

    public static function userNotInvited(): self
    {
        return new self('User has not been invited to this band.');
    }

    public static function userNotDeclined(): self
    {
        return new self('User has not declined an invitation to this band.');
    }

    public static function cannotLeaveOwnedBand(): self
    {
        return new self('Band owner cannot leave their own band. Transfer ownership first.');
    }

    public static function userNotFound(): self
    {
        return new self('User is not associated with this band.');
    }

    public static function invitationNotPending(): self
    {
        return new self('The invitation is not pending and cannot be cancelled.');
    }
}

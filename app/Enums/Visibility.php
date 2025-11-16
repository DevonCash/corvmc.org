<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum Visibility: string implements HasDescription, HasLabel
{
    case Public = 'public';
    case Members = 'members';
    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Members => 'Members Only',
            self::Private => 'Private',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Public => 'Visible to everyone',
            self::Members => 'Visible to logged-in members',
            self::Private => 'Only visible to you and staff',
        };
    }

    public function isVisibleToGuests(): bool
    {
        return $this === self::Public;
    }

    public function isPublic(): bool
    {
        return $this === self::Public;
    }

    public function isMembersOnly(): bool
    {
        return $this === self::Members;
    }

    public function isPrivate(): bool
    {
        return $this === self::Private;
    }
}

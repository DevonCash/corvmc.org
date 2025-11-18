<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Visibility: string implements HasColor, HasDescription, HasIcon, HasLabel
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

    public function getIcon(): string
    {
        return match ($this) {
            self::Public => 'tabler-world',
            self::Members => 'tabler-users',
            self::Private => 'tabler-lock',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Public => 'success',
            self::Members => 'warning',
            self::Private => 'gray',
        };
    }

    /**
     * Check if content with this visibility is visible to guests (non-authenticated users).
     */
    public function isVisibleToGuests(): bool
    {
        return $this === self::Public;
    }

    /**
     * Check if content with this visibility requires authentication.
     */
    public function requiresAuthentication(): bool
    {
        return $this !== self::Public;
    }

    /**
     * Check if this is public visibility.
     */
    public function isPublic(): bool
    {
        return $this === self::Public;
    }

    /**
     * Check if this is members-only visibility.
     */
    public function isMembersOnly(): bool
    {
        return $this === self::Members;
    }

    /**
     * Check if this is private visibility.
     */
    public function isPrivate(): bool
    {
        return $this === self::Private;
    }

    /**
     * Get the visibility levels that are visible to guests.
     */
    public static function visibleToGuests(): array
    {
        return [self::Public];
    }

    /**
     * Get the visibility levels that are visible to authenticated members.
     */
    public static function visibleToMembers(): array
    {
        return [self::Public, self::Members];
    }

    /**
     * Get all visibility options as an array of values.
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}

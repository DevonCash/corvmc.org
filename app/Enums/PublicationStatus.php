<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PublicationStatus: string implements HasLabel, HasIcon, HasColor
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'tabler-file-minus',
            self::Scheduled => 'tabler-clock',
            self::Published => 'tabler-check',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'warning',
            self::Published => 'success',
        };
    }
}

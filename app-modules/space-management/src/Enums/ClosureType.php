<?php

namespace CorvMC\SpaceManagement\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ClosureType: string implements HasColor, HasIcon, HasLabel
{
    case Maintenance = 'maintenance';
    case Holiday = 'holiday';
    case PrivateEvent = 'private';
    case Weather = 'weather';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::Holiday => 'Holiday',
            self::PrivateEvent => 'Private Event',
            self::Weather => 'Weather',
            self::Other => 'Other',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Maintenance => 'tabler-tool',
            self::Holiday => 'tabler-calendar-star',
            self::PrivateEvent => 'tabler-lock',
            self::Weather => 'tabler-cloud-storm',
            self::Other => 'tabler-alert-circle',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::Maintenance => 'warning',
            self::Holiday => 'info',
            self::PrivateEvent => 'gray',
            self::Weather => 'danger',
            self::Other => 'gray',
        };
    }
}

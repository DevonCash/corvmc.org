<?php

namespace CorvMC\Moderation\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReportStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Upheld = 'upheld';
    case Dismissed = 'dismissed';
    case Escalated = 'escalated';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Upheld => 'Upheld',
            self::Dismissed => 'Dismissed',
            self::Escalated => 'Escalated',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Upheld => 'success',
            self::Dismissed => 'gray',
            self::Escalated => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'tabler-clock',
            self::Upheld => 'tabler-check',
            self::Dismissed => 'tabler-x',
            self::Escalated => 'tabler-alert-triangle',
        };
    }
}

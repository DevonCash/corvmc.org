<?php

namespace App\Filament\Shared\Components;

use Filament\Forms\Components\Select;

class MemberSelector
{
    public static function make(string $name): Select
    {
        return Select::make($name)
            ->label('Member')
            ->searchable()
            ->preload();
    }
}

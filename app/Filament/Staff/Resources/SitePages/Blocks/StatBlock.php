<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class StatBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('label')
                    ->required(),
                TextInput::make('value')
                    ->required(),
            ]),

            TextInput::make('subtitle'),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return ($data['label'] ?? '').': '.($data['value'] ?? '');
    }
}

<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class ButtonBlock
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
                TextInput::make('url')
                    ->required()
                    ->placeholder('/events'),
            ]),

            Select::make('color')
                ->options([
                    'primary' => 'Primary',
                    'secondary' => 'Secondary',
                    'info' => 'Info',
                    'success' => 'Success',
                    'warning' => 'Warning',
                ])
                ->default('primary'),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return ($data['label'] ?? '').' → '.($data['url'] ?? '');
    }
}

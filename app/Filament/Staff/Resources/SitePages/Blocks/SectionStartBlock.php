<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

class SectionStartBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Grid::make(4)->schema([
                Select::make('background_color')
                    ->options([
                        'none' => 'None',
                        'success' => 'Success (green)',
                        'primary' => 'Primary (blue)',
                        'info' => 'Info (cyan)',
                        'warning' => 'Warning (amber)',
                        'secondary' => 'Secondary',
                        'accent' => 'Accent',
                    ])
                    ->default('none')
                    ->required(),

                Select::make('columns')
                    ->options([
                        1 => '1 column',
                        2 => '2 columns',
                        3 => '3 columns',
                        4 => '4 columns',
                    ])
                    ->default(2)
                    ->required(),

                Toggle::make('full_bleed')
                    ->label('Full bleed (hero)')
                    ->default(false),
            ]),
        ];
    }

    public static function previewLabel(array $data): string
    {
        $bg = $data['background_color'] ?? 'none';
        $cols = $data['columns'] ?? 2;

        return ucfirst($bg)." · {$cols} col";
    }
}

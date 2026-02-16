<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\TextInput;

class StepBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('icon')
                ->placeholder('tabler-number-1'),

            TextInput::make('title')
                ->required(),

            TextInput::make('description'),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return $data['title'] ?? '';
    }
}

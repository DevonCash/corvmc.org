<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class HeaderBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('heading')
                ->required(),
            Textarea::make('description')
                ->rows(2),
            TextInput::make('icon')
                ->placeholder('tabler-music'),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return $data['heading'] ?? '';
    }
}

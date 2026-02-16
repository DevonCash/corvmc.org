<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

class AlertBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('icon')
                ->placeholder('tabler-info-circle'),

            TextInput::make('text')
                ->required(),

            Select::make('style')
                ->options([
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'success' => 'Success',
                    'error' => 'Error',
                ])
                ->default('info'),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return Str::limit($data['text'] ?? '', 30);
    }
}

<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class CardBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('icon')
                    ->placeholder('tabler-music'),
                TextInput::make('heading')
                    ->required(),
            ]),

            MarkdownEditor::make('body'),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return $data['heading'] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public static function colorOptions(): array
    {
        return [
            'base' => 'Base',
            'success' => 'Success (green)',
            'primary' => 'Primary (blue)',
            'info' => 'Info (cyan)',
            'warning' => 'Warning (amber)',
            'secondary' => 'Secondary',
            'accent' => 'Accent',
        ];
    }
}

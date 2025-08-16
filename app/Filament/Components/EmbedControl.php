<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;

class EmbedControl
{
    public static function make(string $name = 'embeds'): Repeater
    {
        return Repeater::make($name)
            ->hiddenLabel()
            ->label('Featured Content')
            ->reorderable()
            ->schema([
                TextInput::make('url')
                    ->hiddenLabel()
                    ->required()
                    ->maxLength(1000)
                    ->placeholder('Paste YouTube, Spotify, Bandcamp, or SoundCloud embed code')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) {
                        // Extract URL from iframe if pasted
                        if (is_string($state) && str_contains($state, '<iframe')) {
                            if (preg_match('/src=["\']([^"\']+)["\']/', $state, $matches)) {
                                $set('url', $matches[1]);
                            }
                        }
                    })
            ])
            ->table([
                TableColumn::make('URL')->alignLeft(),
            ])
            ->columnSpanFull()
            ->defaultItems(0)
            ->maxItems(5);
    }
}
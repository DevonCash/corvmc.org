<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

class StaffProfileForm
{
    public static function configure($schema)
    {
        return $schema->components([
            Grid::make(1)->schema([
                Group::make([
                    TextInput::make('name')
                        ->label('Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter full name'),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255)
                        ->placeholder('Optional public email'),
                ])->columns(2)
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Staff Title')
                            ->maxLength(255)
                            ->placeholder('e.g. Board President, Operations Manager')
                            ->helperText('Leave blank for board members without specific titles'),

                        Select::make('type')
                            ->label('Staff Type')
                            ->options([
                                'board' => 'Board Member',
                                'staff' => 'Staff Member',
                            ])
                            ->required(),
                    ]),

                Textarea::make('bio')
                    ->label('Staff Bio')
                    ->rows(3)
                    ->maxLength(1000)
                    ->placeholder('Brief description of role and background'),
                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first')
                    ->visible(fn () => Auth::user()?->can('manage staff profiles')),

                Toggle::make('is_active')
                    ->label('Show on About Page')
                    ->default(true)
                    ->helperText('Toggle whether this profile appears on the public about page')
                    ->visible(fn () => Auth::user()?->can('manage staff profiles')),
                SpatieMediaLibraryFileUpload::make('profile_image')
                    ->label('Profile Picture')
                    ->collection('profile_image')
                    ->disk('r2')
                    ->alignCenter()
                    ->avatar(),
                Repeater::make('social_links')
                    ->label('Social Links')
                    ->table([
                        TableColumn::make('Platform'),
                        TableColumn::make('URL'),
                    ])
                    ->schema([
                        Select::make('platform')
                            ->required()
                            ->options([
                                'website' => 'Website',
                                'linkedin' => 'LinkedIn',
                                'twitter' => 'Twitter',
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'github' => 'GitHub',
                            ]),

                        TextInput::make('url')
                            ->required()
                            ->url()
                            ->placeholder('https://...'),
                    ])
                    ->addActionLabel('Add Social Link')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null),
            ]),
        ]);
    }
}

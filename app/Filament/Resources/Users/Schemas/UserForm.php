<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->hiddenOn('create'),
                        TextInput::make('pronouns')
                            ->hiddenOn('create'),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->helperText('An invitation email will be sent to this address'),
                        Select::make('roles')
                            ->label('Initial Roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->options(Role::all()->pluck('name', 'id'))
                            ->preload()
                            ->searchable()
                            ->helperText('Roles that will be assigned when the user accepts the invitation'),
                        DateTimePicker::make('email_verified_at')
                            ->hiddenOn('create'),
                    ]),

                Section::make('Staff Profile')
                    ->schema([
                        Toggle::make('show_on_about_page')
                            ->label('Show on About Page')
                            ->helperText('Display this user in the Leadership section of the About page')
                            ->reactive(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('staff_title')
                                    ->label('Staff Title')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Board President, Operations Manager (optional)')
                                    ->helperText('Leave blank for board members without specific titles')
                                    ->visible(fn ($get) => $get('show_on_about_page')),
                                
                                Select::make('staff_type')
                                    ->label('Staff Type')
                                    ->options([
                                        'board' => 'Board Member',
                                        'staff' => 'Staff Member',
                                    ])
                                    ->visible(fn ($get) => $get('show_on_about_page')),
                            ]),

                        Textarea::make('staff_bio')
                            ->label('Staff Bio')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Brief description of role and background')
                            ->visible(fn ($get) => $get('show_on_about_page')),

                        TextInput::make('staff_sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first')
                            ->visible(fn ($get) => $get('show_on_about_page')),

                        Repeater::make('staff_social_links')
                            ->label('Social Links')
                            ->schema([
                                Grid::make(2)
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
                                    ]),
                            ])
                            ->addActionLabel('Add Social Link')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null)
                            ->visible(fn ($get) => $get('show_on_about_page')),
                    ])
                    ->collapsible()
                    ->hiddenOn('create'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->revealable()
                            ->minLength(8),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at'),
                    ])
                    ->columns(2),

                Section::make('Profile Information')
                    ->relationship('profile')
                    ->schema([
                        TextInput::make('pronouns')
                            ->maxLength(50),
                        TextInput::make('hometown')
                            ->maxLength(255),
                        Textarea::make('bio')
                            ->rows(3)
                            ->maxLength(1000),
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'members' => 'Members Only',
                                'private' => 'Private',
                            ])
                            ->default('members')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Roles & Permissions')
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Assign roles to grant specific permissions'),
                    ]),
            ]);
    }
}

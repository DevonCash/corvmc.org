<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            ]);
    }
}

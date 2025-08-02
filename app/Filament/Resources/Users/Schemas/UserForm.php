<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->headerActions([
                        Action::make('edit_profile')
                            ->label('Edit Profile')
                            ->size('sm')

                            ->icon('heroicon-o-pencil-square')
                            ->iconPosition(IconPosition::After)
                            ->url(fn ($record) => route('filament.member.resources.directory.edit', $record->profile->id))
                            ->visible(fn ($record) => $record && $record->profile),
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->suffixIcon(fn ($record) => $record && $record->email_verified_at ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->suffixIconColor(fn ($record) => $record && $record->email_verified_at ? 'success' : 'danger')
                            ->hint(
                                fn ($record) => $record && $record->email_verified_at
                                    ? $record->email_verified_at->format('M j Y') : ''
                            ),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                    ])
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->contained(false)
                    ->tabs([
                        Tab::make('Account')
                            ->schema([
                                Section::make('')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('name')
                                                ->required()
                                                ->columnSpan(2)
                                                ->hiddenOn('create'),
                                            TextInput::make('pronouns')
                                                ->hiddenOn('create'),
                                        ]),
                                        TextInput::make('email')
                                            ->label('Email address')
                                            ->email()
                                            ->required()
                                            ->suffixIcon(fn($record) => $record->email_verified_at ? 'tabler-circle-check' : 'tabler-circle-x')
                                            ->suffixIconColor(fn($record) => $record->email_verified_at ? 'success' : 'danger')
                                            ->hint(fn($record) => $record->email_verified_at ? 'Verified' : 'Unverified'),
                                        Select::make('roles')
                                            ->label('Roles')
                                            ->multiple()
                                            ->relationship('roles', 'name')
                                            ->options(Role::all()->pluck('name', 'id'))
                                            ->preload()
                                            ->searchable()
                                    ])
                            ]),
                        Tab::make('Member Profile')
                            ->schema([
                                MemberProfileForm::configure(Section::make('')->relationship('profile'))
                            ]),
                        Tab::make('Staff Profile')
                            ->schema([
                                StaffProfileForm::configure(Section::make(''))
                                    ->hiddenOn('create'),
                            ]),

                    ]),

            ]);
    }
}

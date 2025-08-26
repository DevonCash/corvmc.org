<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\TextEntry;
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
                                Section::make('')->schema([
                                    Text::make('No staff profile exists for this user.')
                                        ->extraAttributes(['class' => 'text-center']),
                                    Action::make('create_staff_profile')
                                        ->label('Add Staff Profile')
                                        ->icon('heroicon-o-plus')
                                        ->color('primary')
                                        ->action(function ($livewire) {
                                            $record = $livewire->getRecord();
                                            if ($record && !$record->staffProfile) {
                                                $record->staffProfile()->create([
                                                    'name' => $record->name,
                                                    'email' => $record->email,
                                                    'type' => 'staff',
                                                    'is_active' => false,
                                                    'sort_order' => 0,
                                                ]);
                                                $record->refresh();
                                                $livewire->form->fill($record->toArray());
                                            }
                                        })
                                ])
                                    ->visible(fn($record) => !$record?->staffProfile),
                                StaffProfileForm::configure(Section::make('')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('is_active')
                                            ->label('Show on About Page')
                                            ->helperText('Display this user in the Leadership section of the About page')
                                            ->live(),
                                    ])
                                    ->relationship('staffProfile')
                                    ->visible(fn($record) => $record?->staffProfile))

                            ]),

                    ]),

            ]);
    }
}

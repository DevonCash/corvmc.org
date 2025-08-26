<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Filament\Resources\Users\Schemas\StaffProfileForm;
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
use App\Models\User;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->contained(false)
                    ->persistTab()
                    ->id('user_form_tabs')
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
                                            ->visible(fn() => User::me()?->can('update user roles'))
                                            ->searchable()
                                    ]),
                                Section::make('')
                                    ->schema([
                                        Action::make('send_password_reset')
                                            ->label('Reset Password')
                                            ->icon('heroicon-o-key')
                                            ->color('info')
                                            ->requiresConfirmation()
                                            ->modalHeading('Reset Password')
                                            ->modalDescription(fn($record) => "Send a password reset email to {$record->email}?")
                                            ->modalSubmitActionLabel('Send Reset Email')
                                            ->action(function(User $record) {

                                                $record->sendPasswordResetNotification();
                                            })
                                    ])
                                    ->visible(fn($record) => User::me()->is($record) || User::me()?->can('update users'))
                            ]),
                        Tab::make('Member Profile')
                            ->schema([
                                MemberProfileForm::configure(Section::make('')->relationship('profile'))
                            ]),
                        Tab::make('Staff Profile')
                            ->visible(fn() => User::me()?->hasRole('admin') || User::me()?->can('update users'))
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

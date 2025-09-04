<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\MemberProfiles\Schemas\MemberProfileForm;
use App\Filament\Resources\Users\Schemas\AdminUserControlForm;
use App\Filament\Resources\Users\Schemas\StaffProfileForm;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

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
                                        Flex::make([
                                            TextInput::make('name')
                                                ->required()
                                                ->grow()
                                                ->hiddenOn('create'),
                                            TextInput::make('pronouns')
                                                ->hiddenOn('create'),
                                        ]),
                                        Flex::make([
                                            TextInput::make('email')
                                                ->label('Email address')
                                                ->email()
                                                ->required()
                                                ->suffixAction(
                                                    fn($record) => $record?->email_verified_at ?
                                                        null :
                                                        Action::make('send_verification')
                                                        ->icon('heroicon-o-envelope')
                                                        ->color('primary')
                                                        ->tooltip('Send verification email')
                                                        ->action(function ($record) {
                                                            $record->sendEmailVerificationNotification();
                                                        })
                                                )
                                                ->suffixIcon(fn($record) => $record?->email_verified_at ? 'tabler-circle-check' : null)
                                                ->suffixIconColor('success')
                                                ->hint(fn($record) => $record?->email_verified_at ? 'Verified' : 'Unverified'),
                                            Action::make('send_password_reset')
                                                ->label('Reset Password')
                                                ->icon('heroicon-o-key')
                                                ->color('info')
                                                ->requiresConfirmation()
                                                ->modalHeading('Reset Password')
                                                ->modalDescription(fn($record) => "Send a password reset email to {$record->email}?")
                                                ->modalSubmitActionLabel('Send Reset Email')
                                                ->action(function (User $record) {

                                                    $record->sendPasswordResetNotification();
                                                }),
                                        ])->verticalAlignment('end')
                                    ]),
                                MembershipForm::configure(Grid::make(1))
                                    ->visible(fn($record) => $record !== null),

                            ]),
                        Tab::make('Member Profile')
                            ->schema([
                                MemberProfileForm::configure(Section::make('')->relationship('profile')),
                            ]),
                        Tab::make('Staff Profile')
                            ->visible(fn($record) => $record?->staffProfile && (User::me()?->can('manage staff profiles') || User::me()->is($record)))
                            ->schema([
                                StaffProfileForm::configure(Section::make('')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('is_active')
                                            ->label('Show on About Page')
                                            ->helperText('Display this user in the Leadership section of the About page')
                                            ->live(),
                                    ])
                                    ->relationship('staffProfile')),
                            ]),
                        Tab::make('Administration')
                            ->visible(fn($record) => User::me()?->can('update user roles') || User::me()?->can('manage subscriptions'))
                            ->schema([
                                AdminUserControlForm::configure(Grid::make(1)),
                            ]),

                    ]),

            ]);
    }
}

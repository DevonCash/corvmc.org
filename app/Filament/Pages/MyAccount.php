<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MyAccount extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.simple-form-page';

    protected static string | \UnitEnum | null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'account';

    public ?array $data = [];

    public function mount(): void
    {
        $user = User::me();
        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'pronouns' => $user->pronouns,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('logout')
                ->label('Logout')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('danger')
                ->action(function () {
                    Auth::guard('web')->logout();
                    request()->session()->invalidate();
                    request()->session()->regenerateToken();
                    return redirect('/');
                }),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Account Information')
                    ->description('Update your account details')
                    ->schema([
                        Flex::make([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->grow()
                                ->maxLength(255),
                            TextInput::make('pronouns')
                                ->label('Pronouns')
                                ->maxLength(50),
                        ]),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Change Password')
                    ->description('Update your password')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->currentPassword(),

                        TextInput::make('password')
                            ->label('New Password')
                            ->password()
                            ->revealable()
                            ->confirmed()
                            ->minLength(8),

                        TextInput::make('password_confirmation')
                            ->label('Confirm New Password')
                            ->password()
                            ->revealable(),
                    ]),
            ])
            ->model(User::me())
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = User::me();

        // Update basic info
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'pronouns' => $data['pronouns'] ?? null,
        ]);

        // Update password if provided
        if (!empty($data['password'])) {
            $user->update([
                'password' => Hash::make($data['password']),
            ]);
        }

        Notification::make()
            ->success()
            ->title('Account updated')
            ->send();

        // Clear password fields
        $this->form->fill([
            'name' => $user->name,
            'email' => $user->email,
            'pronouns' => $user->pronouns,
        ]);
    }

}

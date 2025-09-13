<?php

namespace App\Livewire;

use App\Facades\UserInvitationService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class InvitationAcceptForm extends Component implements HasForms
{
    use InteractsWithForms;

    public string $token;

    public array $data = [
        'name' => '',
        'password' => '',
        'password_confirmation' => '',
    ];

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->label('Full Name')
                    ->placeholder('Enter your full name')
                    ->required()
                    ->autofocus()
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Password')
                    ->placeholder('Create a secure password')
                    ->password()
                    ->required()
                    ->rule(Password::defaults())
                    ->same('password_confirmation'),

                TextInput::make('password_confirmation')
                    ->label('Confirm Password')
                    ->placeholder('Confirm your password')
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->form->getState();

        $user = UserInvitationService::acceptInvitation($this->token, [
            'name' => $this->data['name'],
            'password' => $this->data['password'],
        ]);

        if (!$user) {
            Notification::make()
                ->title('Invalid or Expired Invitation')
                ->body('This invitation is invalid or has expired.')
                ->danger()
                ->send();
            return;
        }

        // Log the user in automatically
        Auth::login($user);

        // Redirect to member dashboard
        $this->redirect('/member', navigate: false);
    }

    public function render()
    {
        return view('livewire.invitation-accept-form');
    }
}

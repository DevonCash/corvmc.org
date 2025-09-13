<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Traits\HasCrudService;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;
use Filament\Notifications\Notification;

class EditUser extends EditRecord
{
    use HasCrudService;

    protected static string $resource = UserResource::class;
    protected static ?string $crudService = 'UserService';

    protected function getHeaderActions(): array
    {
        $actions = [
            DeleteAction::make(),
        ];

        // Add logout action if user is editing their own profile
        if ($this->getRecord()->id === auth()->id()) {
            $actions[] = Action::make('logout')
                ->label('Logout')
                ->icon('heroicon-m-arrow-left-on-rectangle')
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Logout')
                ->modalDescription('Are you sure you want to logout?')
                ->modalSubmitActionLabel('Logout')
                ->action(function () {
                    auth()->logout();
                    request()->session()->invalidate();
                    request()->session()->regenerateToken();
                    return redirect()->route('filament.member.auth.login');
                });
        }

        return $actions;
    }

    public function sendPasswordReset(): void
    {
        $user = $this->getRecord();
        
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            Notification::make()
                ->title('Password reset sent')
                ->body("Password reset email has been sent to {$user->email}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Failed to send password reset')
                ->body('There was an error sending the password reset email. Please try again.')
                ->danger()
                ->send();
        }
    }
}

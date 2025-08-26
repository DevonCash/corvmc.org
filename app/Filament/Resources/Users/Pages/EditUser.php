<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;
use Filament\Notifications\Notification;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
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

<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_password_reset')
                ->label('Send Password Reset')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->action(function () {
                    $status = Password::sendResetLink(['email' => $this->record->email]);

                    if ($status === Password::RESET_LINK_SENT) {
                        Notification::make()
                            ->title('Password reset link sent')
                            ->body('A password reset link has been sent to '.$this->record->email)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Failed to send password reset')
                            ->body('Could not send password reset link. Please try again.')
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Send Password Reset Link')
                ->modalDescription('This will send a password reset link to the user\'s email address.')
                ->modalSubmitActionLabel('Send Reset Link'),
            DeleteAction::make(),
        ];
    }
}

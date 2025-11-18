<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\DeleteUser as DeleteUserAction;
use App\Actions\Users\UpdateUser as UpdateUserAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;

/**
 * @property \App\Models\User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return UpdateUserAction::run($record, $data);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        DeleteUserAction::run($record);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            DeleteAction::make(),
        ];

        // Add logout action if user is editing their own profile
        if ($this->record->id === auth()->id()) {
            $actions[] = Action::make('logout')
                ->label('Logout')
                ->icon('tabler-logout')
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
        $user = $this->record;

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

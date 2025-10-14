<?php

namespace App\Actions\Bands;

use App\Concerns\AsFilamentAction;
use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class ReinviteBandMember
{
    use AsAction;
    use AsFilamentAction;
    public function handle(Band $band, User $user): void
    {
        if (! $band->memberships()->declined()->for($user)->exists()) {
            throw BandException::userNotDeclined();
        }

        // Update the invited_at timestamp
        $band->members()->updateExistingPivot($user->id, [
            'invited_at' => now(),
            'status' => 'invited',
        ]);

        // Get the current invitation details
        $member = $band->members()->wherePivot('user_id', $user->id)->first();

        // Resend notification
        $user->notify(new BandInvitationNotification(
            $band,
            $member->pivot->role,
            $member->pivot->position
        ));
    }

    public static function filamentAction(): Action
    {
        return Action::make('reinvite')
            ->label('Reinvite')
            ->color('warning')
            ->icon('tabler-mail-forward')
            ->authorize('reinvite')
            ->action(function ($record): void {
                $user = User::find($record->user_id);
                static::run($record->band, $user);
                Notification::make()
                    ->title('Invitation resent')
                    ->success()
                    ->send();
            });
    }
}

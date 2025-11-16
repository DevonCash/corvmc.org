<?php

namespace App\Actions\Bands;

use App\Concerns\AsFilamentAction;
use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationAcceptedNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AcceptBandInvitation
{
    use AsAction;
    use AsFilamentAction;

    /**
     * Accept an invitation to join a band.
     */
    public function handle(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotInvited();
        }

        DB::transaction(function () use ($band, $user) {
            // Update status to active
            $band->members()->updateExistingPivot($user->id, [
                'status' => 'active',
            ]);
            // Notify band owner and admins about the new member
            $this->notifyBandLeadership($band, $user);
        });
    }

    /**
     * Notify band leadership (owner and admins) about membership changes.
     */
    private function notifyBandLeadership(Band $band, User $user): void
    {
        $adminMembers = $band->memberships()
            ->active()
            ->where('role', 'admin')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        $adminsAndOwner = $adminMembers
            ->push($band->owner)
            ->unique('id')
            ->filter(fn ($u) => $u->id !== $user->id); // Don't notify the person who just joined

        foreach ($adminsAndOwner as $admin) {
            $admin->notify(new BandInvitationAcceptedNotification($band, $user));
        }
    }

    public static function filamentAction(): Action
    {
        return static::buildBaseAction()
            ->label('Accept')
            ->color('success')
            ->icon('tabler-check')
            ->requiresConfirmation()
            ->modalHeading('Accept Band Invitation')
            ->modalDescription(fn ($record) => "Accept invitation to join {$record->band->name}?")
            ->action(function ($record): void {
                $user = User::find($record->user_id);
                static::run($record->band, $user);
                Notification::make()
                    ->title('Invitation accepted')
                    ->body("Welcome to {$record->band->name}!")
                    ->success()
                    ->send();
            })
            ->authorize('accept');
    }
}

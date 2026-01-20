<?php

namespace App\Actions\Bands;

use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationAcceptedNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/** @package App\Actions\Bands */
class AcceptBandInvitation
{
    use AsAction;

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
        });

        // Notify band owner and admins about the new member (outside transaction)
        $this->notifyBandLeadership($band, $user);
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
            try {
                $admin->notify(new BandInvitationAcceptedNotification($band, $user));
            } catch (\Exception $e) {
                Log::error('Failed to send band invitation accepted notification', [
                    'band_id' => $band->id,
                    'user_id' => $user->id,
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function filamentAction(): Action
    {
        return Action::make('accept_band_invitation')
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

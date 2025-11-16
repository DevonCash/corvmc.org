<?php

namespace App\Filament\Resources\CommunityEvents\Actions;

use App\Models\CommunityEvent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ApproveCommunityEventAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->icon('tabler-check')
            ->color('success')
            ->visible(fn (CommunityEvent $record): bool => $record->status === CommunityEvent::STATUS_PENDING &&
                Auth::user()->can('approve community events'))
            ->requiresConfirmation()
            ->action(function (CommunityEvent $record, Action $action) {
                $record->update([
                    'status' => CommunityEvent::STATUS_APPROVED,
                    'published_at' => now(),
                ]);

                Notification::make()
                    ->title('Event approved successfully')
                    ->success()
                    ->send();

                // Redirect to the updated record
                return redirect($action->getLivewire()->getResource()::getUrl('view', ['record' => $record]));
            });
    }
}

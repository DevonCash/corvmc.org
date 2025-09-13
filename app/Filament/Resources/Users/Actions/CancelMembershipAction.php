<?php

namespace App\Filament\Resources\Users\Actions;

use App\Services\UserSubscriptionService;
use Filament\Actions\Action;

class CancelMembershipAction
{
    public static function make(): Action
    {
        return Action::make('cancel_membership')
            ->label('Cancel Contribution')
            ->icon('heroicon-o-x-mark')
            ->outlined()
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel Membership')
            ->modalDescription('Are you sure you want to cancel your membership? You will retain access until the end of your current billing period.')
            ->modalSubmitActionLabel('Yes, Cancel Membership')
            // ->visible(fn($record) => $record?->subscription('default')?->active())

            ->action(function ($record) {
                $result = \UserSubscriptionService::cancelSubscription($record);

                if ($result['success']) {
                    \Filament\Notifications\Notification::make()
                        ->title('Membership Cancelled')
                        ->body($result['message'])
                        ->success()
                        ->send();
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('Failed to cancel membership')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                }
            });
    }
}

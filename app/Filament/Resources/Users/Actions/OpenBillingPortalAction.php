<?php

namespace App\Filament\Resources\Users\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

class OpenBillingPortalAction
{
    public static function make(): Action
    {
        return Action::make('open_billing_portal')
            ->label('Manage Billing')
            ->icon('tabler-credit-card')
            ->color('info')
            ->action(function ($record) {
                if (! $record->stripe_id) {
                    Notification::make()
                        ->title('No billing account')
                        ->body('You need an active subscription to access billing management.')
                        ->warning()
                        ->send();

                    return;
                }
                // Return to the user's view page after billing portal
                $returnUrl = url("/member/users/{$record->id}");
                $billingPortal = $record->billingPortalUrl($returnUrl);

                // Redirect to the billing portal
                return redirect()->away($billingPortal);
            })
            ->disabled(fn ($record) => $record->stripe_id == null);
    }
}

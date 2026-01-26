<?php

namespace App\Filament\Staff\Resources\Users\Actions;

use App\Models\User;
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
            ->action(function () {
                $user = User::me();

                if (! $user->stripe_id) {
                    Notification::make()
                        ->title('No billing account')
                        ->body('You need an active subscription to access billing management.')
                        ->warning()
                        ->send();

                    return;
                }
                // Return to the membership page after billing portal
                $returnUrl = route('filament.member.pages.membership');
                $billingPortal = $user->billingPortalUrl($returnUrl);

                // Redirect to the billing portal
                return redirect()->away($billingPortal);
            })
            ->disabled(fn () => User::me()->stripe_id == null);
    }
}

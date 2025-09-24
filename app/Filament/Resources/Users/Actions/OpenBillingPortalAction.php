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
                if (!$record->stripe_id) {
                    Notification::make()
                        ->title('No billing account')
                        ->body('You need an active subscription to access billing management.')
                        ->warning()
                        ->send();
                    return;
                }

                try {
                    // Return to the user's view page after billing portal
                    $returnUrl = url("/member/users/{$record->id}");
                    $billingPortal = $record->billingPortalUrl($returnUrl);

                    // Redirect to the billing portal
                    return redirect()->away($billingPortal);
                } catch (\Exception $e) {
                    // Log the error for debugging
                    \Log::warning('Failed to generate billing portal URL', [
                        'user_id' => $record->id,
                        'stripe_id' => $record->stripe_id,
                        'error' => $e->getMessage()
                    ]);

                    Notification::make()
                        ->title('Unable to access billing portal')
                        ->body('The billing portal is currently unavailable. Please contact support.')
                        ->danger()
                        ->send();
                }
            })
            ->disabled(fn($record) => $record->stripe_id == null);
    }
}

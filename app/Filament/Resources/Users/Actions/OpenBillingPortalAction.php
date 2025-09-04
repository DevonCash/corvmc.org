<?php

namespace App\Filament\Resources\Users\Actions;

use Filament\Actions\Action;

class OpenBillingPortalAction
{
    public static function make(): Action
    {
        return Action::make('open_billing_portal')
            ->label('Manage Billing')
            ->icon('heroicon-o-credit-card')
            ->color('info')
            ->url(function ($record) {
                if (!$record->stripe_id) {
                    return null;
                }

                try {
                    $billingPortal = $record->billingPortalUrl(route('filament.member.auth.profile'));
                    return $billingPortal;
                } catch (\Exception $e) {
                    return null;
                }
            })
            ->openUrlInNewTab()
            ->disabled(fn($record) => $record->stripe_id == null);
    }
}

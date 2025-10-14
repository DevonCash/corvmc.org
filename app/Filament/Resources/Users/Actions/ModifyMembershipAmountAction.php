<?php

namespace App\Filament\Resources\Users\Actions;

use Brick\Money\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\RawJs;

class ModifyMembershipAmountAction
{
    public static function make(): Action
    {
        return Action::make('modify_membership_amount')
            ->label('Update Contribution')
            ->icon('tabler-cash-banknote')
            ->color('primary')
            ->modalWidth('lg')
            ->schema([
                Slider::make('amount')
                    ->label('New Monthly Contribution ($10 - $50)')
                    ->minValue(10)
                    ->maxValue(50)
                    ->step(5)
                    ->fillTrack()
                    ->live()
                    ->tooltips(RawJs::make('`$${$value.toFixed(2)}`'))
                    ->default(function ($record) {
                        $subscription = \App\Actions\Subscriptions\GetActiveSubscription::run($record);

                        if ($subscription) {
                            // Get the Stripe subscription object with pricing info
                            $stripeSubscription = $subscription->asStripeSubscription();
                            $firstItem = $stripeSubscription->items->data[0];
                            $currentAmount = $firstItem->price->unit_amount / 100; // Convert from cents to dollars
                            // Clamp to slider range
                            return max(10, min(50, $currentAmount));
                        }

                        return 25;
                    })
                    ->pips(PipsMode::Steps)
                    ->required(),
                Toggle::make('cover_fees')
                    ->label('Cover Processing Fees')
                    ->columnSpan(2)
                    ->helperText(function ($get) {
                        $amount = Money::of($get('amount'), 'USD');
                        if (!$amount->isZero()) {
                            $feeInfo = \App\Actions\Payments\GetFeeDisplayInfo::run($amount);

                            return $feeInfo['message'];
                        }

                        return 'Add processing fees to support the organization';
                    })
                    ->live()
                    ->default(function ($record) {
                        $subscription = \App\Actions\Subscriptions\GetActiveSubscription::run($record);

                        if ($subscription) {
                            // Check if subscription has multiple items (base + fee coverage)
                            $stripeSubscription = $subscription->asStripeSubscription();
                            return count($stripeSubscription->items->data) > 1;
                        }

                        return false;
                    }),
                TextEntry::make('total_preview')
                    ->label('New Monthly Total')
                    ->state(function ($get) {
                        $amount = Money::of($get('amount') ?: 0, 'USD');
                        if ($amount->isZero()) {
                            return 'Please select a contribution amount';
                        }

                        $breakdown = \App\Actions\Payments\GetFeeBreakdown::run($amount, $get('cover_fees'));
                        $totalAmount = Money::of($breakdown['total_amount'], 'USD');

                        return $breakdown['description'] . ' = ' . \App\Actions\Payments\FormatMoney::run($totalAmount) . ' total per month';
                    })
                    ->extraAttributes(['class' => 'text-lg font-semibold text-primary-600']),
            ])
            ->modalSubmitActionLabel('Update Contribution')
            ->modalCancelAction(false)
            ->action(function (array $data, $record) {
                $baseAmount = Money::of($data['amount'], 'USD');

                \App\Actions\Subscriptions\UpdateSubscriptionAmount::run($record, $baseAmount, $data['cover_fees']);
                \Filament\Notifications\Notification::make()
                    ->title('Membership Updated')
                    ->success()
                    ->send();
            });
    }
}

<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Brick\Money\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\RawJs;

class CreateMembershipSubscriptionAction
{
    public static function make(): Action
    {
        return Action::make('create_membership_subscription')
            ->label('Become a Sustaining Member')
            ->icon('tabler-user-heart')
            ->color('primary')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Checkout')
            ->closeModalByClickingAway(false)
            ->schema([
                Slider::make('amount')
                    ->label('Monthly Contribution ($10 - $60)')
                    ->minValue(10)
                    ->maxValue(60)
                    ->step(5)
                    ->fillTrack()
                    ->live()
                    ->tooltips(RawJs::make('`$${$value.toFixed(2)}`'))
                    ->default(25)
                    ->pips(PipsMode::Steps)
                    ->required(),
                Toggle::make('cover_fees')
                    ->label('Cover Processing Fees')
                    ->columnSpan(2)
                    ->helperText(function ($get) {
                        $amount = Money::of($get('amount'), 'USD');
                        if (! $amount->isZero()) {
                            $feeInfo = \App\Actions\Payments\GetFeeDisplayInfo::run($amount);

                            return $feeInfo['message'];
                        }

                        return 'Add processing fees to support the organization';
                    })
                    ->live()
                    ->default(false),
                TextEntry::make('total_preview')
                    ->label('Monthly Total')
                    ->state(function ($get) {
                        $amount = Money::of($get('amount') ?: 0, 'USD');
                        if ($amount->isZero()) {
                            return 'Please select a contribution amount';
                        }
                        $breakdown = \App\Actions\Payments\GetFeeBreakdown::run($amount, $get('cover_fees'));
                        $totalAmount = Money::of($breakdown['total_amount'], 'USD');

                        return $breakdown['description'].' = '.\App\Actions\Payments\FormatMoney::run($totalAmount).' total per month';
                    })
                    ->extraAttributes(['class' => 'text-lg font-semibold text-primary-600']),
            ])
            ->action(function (array $data) {
                $baseAmount = Money::of($data['amount'], 'USD');
                $checkout = \App\Actions\Subscriptions\CreateSubscription::run(User::me(), $baseAmount, $data['cover_fees']);
                redirect($checkout->url);
            });
    }
}

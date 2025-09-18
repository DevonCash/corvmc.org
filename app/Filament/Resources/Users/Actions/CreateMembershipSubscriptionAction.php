<?php

namespace App\Filament\Resources\Users\Actions;

use App\Facades\PaymentService;
use App\Facades\UserSubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Slider\Enums\PipsMode;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\Log;

class CreateMembershipSubscriptionAction
{
    public static function make(): Action
    {
        return Action::make('create_membership_subscription')
            ->label('Become a Sustaining Member')
            ->icon('heroicon-o-credit-card')
            ->color('primary')
            ->modalWidth('lg')
            ->schema([
                Slider::make('amount')
                    ->label('Monthly Contribution ($10 - $50)')
                    ->minValue(10)
                    ->maxValue(50)
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
                        $amount = $get('amount');
                        if ($amount > 0) {
                            $feeInfo = PaymentService::getFeeDisplayInfo($amount);

                            return $feeInfo['message'];
                        }

                        return 'Add processing fees to support the organization';
                    })
                    ->live()
                    ->default(false),
                TextEntry::make('total_preview')
                    ->label('Monthly Total')
                    ->state(function ($get) {
                        $amount = $get('amount') ?: 0;
                        if ($amount <= 0) {
                            return 'Please select a contribution amount';
                        }

                        $breakdown = PaymentService::getFeeBreakdown($amount, $get('cover_fees'));

                        return $breakdown['description'] . ' = ' . PaymentService::formatMoney($breakdown['total_amount']) . ' total per month';
                    })
                    ->extraAttributes(['class' => 'text-lg font-semibold text-primary-600']),
            ])
            ->action(function (array $data, $record) {
                $baseAmount = floatval($data['amount']);
                $result = UserSubscriptionService::createSubscription($record, $baseAmount, $data['cover_fees']);

                if ($result['success']) {
                    redirect($result['checkout_url']);
                } else {
                    Log::error('Failed to create membership subscription', [
                        'user_id' => $record->id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                    \Filament\Notifications\Notification::make()
                        ->title('Failed to create membership checkout')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                }
            });
    }
}

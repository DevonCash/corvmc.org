<?php

namespace App\Filament\Resources\Users\Actions;

use App\Services\PaymentService;
use App\Services\UserSubscriptionService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Actions;
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
            ->label('Change Contribution')
            ->icon('heroicon-o-banknotes')
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
                        $displayInfo = \UserSubscriptionService::getSubscriptionDisplayInfo($record);

                        if ($displayInfo['has_subscription']) {
                            $currentAmount = $displayInfo['amount'];
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
                        $amount = $get('amount');
                        if ($amount > 0) {
                            $feeInfo = \PaymentService::getFeeDisplayInfo($amount);

                            return $feeInfo['accurate_message'];
                        }

                        return 'Add processing fees to support the organization';
                    })
                    ->live()
                    ->default(false),
                TextEntry::make('total_preview')
                    ->label('New Monthly Total')
                    ->state(function ($get) {
                        $amount = $get('amount') ?: 0;
                        if ($amount <= 0) {
                            return 'Please select a contribution amount';
                        }

                        $breakdown = \PaymentService::getFeeBreakdown($amount, $get('cover_fees'));

                        return $breakdown['description'] . ' = ' . \PaymentService::formatMoney($breakdown['total_amount']) . ' total per month';
                    })
                    ->extraAttributes(['class' => 'text-lg font-semibold text-primary-600']),
            ])
            ->action(function (array $data, $record) {
                $baseAmount = floatval($data['amount']);

                $result = \UserSubscriptionService::updateSubscriptionAmount($record, $baseAmount, $data['cover_fees']);

                if ($result['success']) {
                    \Filament\Notifications\Notification::make()
                        ->title('Membership Updated')
                        ->body($result['message'])
                        ->success()
                        ->send();
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('Failed to update membership')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                }
            });
    }
}

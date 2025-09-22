<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Actions\CancelMembershipAction;
use App\Filament\Resources\Users\Actions\CreateMembershipSubscriptionAction;
use App\Filament\Resources\Users\Actions\ModifyMembershipAmountAction;
use App\Filament\Resources\Users\Actions\OpenBillingPortalAction;
use App\Filament\Resources\Users\Actions\ResumeMembershipAction;
use App\Facades\UserSubscriptionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;

class MembershipForm
{
    public static function configure($schema)
    {
        return $schema
            ->schema([

                // No sustaining membership section - show signup
                Section::make('Sustaining Member')
                    ->description('Become a sustaining member with a sliding scale contribution that fits your budget. Sustaining members receive 4 free practice space hours each month.')
                    ->visible(function ($record) {
                        return !$record->isSustainingMember() && !static::hasActiveOrCancelledSubscription($record);
                    })
                    ->headerActions([
                        CreateMembershipSubscriptionAction::make()
                    ]),

                // Active sustaining membership section
                Section::make('Sustaining Member')
                    ->description('Your sustaining membership is active! You can change your contribution amount or access billing details below.')
                    ->columns(2)
                    ->visible(function ($record) {
                        return $record->isSustainingMember() && !static::isSubscriptionCancelled($record);
                    })
                    ->schema([
                        TextEntry::make('remaining_free_hours')
                            ->label('Free Hours Remaining This Month')
                            ->state(function ($record) {
                                $totalHours = UserSubscriptionService::getUserMonthlyFreeHours($record);
                                return $record->getRemainingFreeHours() . ' / ' . $totalHours . ' hours';
                            }),
                        TextEntry::make('current_subscription')
                            ->label('Current Contribution')
                            ->state(function ($record) {
                                $displayInfo = UserSubscriptionService::getSubscriptionDisplayInfo($record);

                                if ($displayInfo['has_subscription']) {
                                    return sprintf(
                                        '%s/%s - %s',
                                        $displayInfo['formatted_amount'],
                                        $displayInfo['interval'],
                                        $displayInfo['status']
                                    );
                                }

                                return 'No active contribution';
                            })
                            ->badge()
                            ->color('success'),
                        Flex::make([
                            OpenBillingPortalAction::make(),
                            ModifyMembershipAmountAction::make(),
                            CancelMembershipAction::make(),
                        ])->columnSpanFull()
                    ]),

                // Cancelled sustaining membership section
                Section::make('Sustaining Member')
                    ->description(function ($record) {
                        $subscription = static::getActiveSubscription($record);
                        if ($subscription && $subscription->ends_at) {
                            return sprintf(
                                'Your contribution is cancelled and will end on %s. You can resume your contribution anytime before then. You will remain a member of the collective.',
                                $subscription->ends_at->format('F j, Y \a\t g:i A')
                            );
                        }
                        return 'Your contribution has been cancelled. You remain a member of the collective.';
                    })
                    ->columns(2)
                    ->visible(function ($record) {
                        return static::isSubscriptionCancelled($record);
                    })
                    ->schema([
                        TextEntry::make('remaining_free_hours')
                            ->label('Free Hours Remaining This Month')
                            ->state(function ($record) {
                                $totalHours = UserSubscriptionService::getUserMonthlyFreeHours($record);
                                return $record->getRemainingFreeHours() . ' / ' . $totalHours . ' hours';
                            }),
                        TextEntry::make('cancellation_info')
                            ->label('Contribution Status')
                            ->state(function ($record) {
                                $subscription = static::getActiveSubscription($record);
                                if ($subscription && $subscription->ends_at) {
                                    $displayInfo = UserSubscriptionService::getSubscriptionDisplayInfo($record);
                                    return sprintf(
                                        '%s/%s contribution - Ends %s',
                                        $displayInfo['formatted_amount'] ?? 'Unknown',
                                        $displayInfo['interval'] ?? 'month',
                                        $subscription->ends_at->diffForHumans()
                                    );
                                }
                                return 'Contribution cancelled';
                            })
                            ->badge()
                            ->color('warning'),
                        Flex::make([
                            ResumeMembershipAction::make(),
                            OpenBillingPortalAction::make(),
                        ])->columnSpanFull()
                    ]),

            ]);
    }

    /**
     * Check if user has an active or cancelled subscription
     */
    private static function hasActiveOrCancelledSubscription($record): bool
    {
        return static::getActiveSubscription($record) !== null;
    }

    /**
     * Check if user's subscription is cancelled (has ends_at set)
     */
    private static function isSubscriptionCancelled($record): bool
    {
        $subscription = static::getActiveSubscription($record);
        return $subscription && $subscription->ends_at !== null;
    }

    /**
     * Get the user's active subscription (even if cancelled)
     */
    private static function getActiveSubscription($record)
    {
        return $record->subscriptions()
            ->where('stripe_status', 'active')
            ->first();
    }
}

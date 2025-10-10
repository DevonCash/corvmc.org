<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Actions\CreateMembershipSubscriptionAction;
use App\Filament\Resources\Users\Actions\ModifyMembershipAmountAction;
use App\Filament\Resources\Users\Actions\OpenBillingPortalAction;
use App\Filament\Resources\Users\Actions\ResumeMembershipAction;
use App\Facades\UserSubscriptionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Facades\Log;

class MembershipForm
{
    public static function configure($schema)
    {
        return $schema
            ->schema([

                // No sustaining membership section - show signup with benefits summary
                Section::make('Become a Sustaining Member')
                    ->description('Support the Corvallis Music Collective with a monthly contribution!')
                    ->visible(function ($record) {
                        return !$record->isSustainingMember() && !static::hasActiveOrCancelledSubscription($record);
                    })
                    ->columns(3)
                    ->schema([
                        TextEntry::make('Practice')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->state([
                                "Free reservation hours every month (1 hour per $5 contributed)",
                                "Recurring practice reservations"
                            ]),
                        TextEntry::make('Equipment')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->state([
                                "Free accessory rentals (cables, stands, etc.)",
                                "Monthly equipment credits equal to your contribution"
                            ]),
                        TextEntry::make('Community')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->state([
                                "50% off admission to CMC events",
                                "Support local music!"
                            ]),

                        Text::make('Sustaining memberships start at $10/month and can be changed or cancelled anytime.')->columnSpanFull()

                    ])
                    ->headerActions([
                        CreateMembershipSubscriptionAction::make()
                    ]),

                // Active sustaining membership section
                Section::make('Your Membership Contribution')
                    ->description('Your sustaining membership is active! You can change your contribution amount or access billing details below.')
                    ->columns(2)
                    ->visible(function ($record) {
                        return $record->isSustainingMember();
                    })
                    ->schema([
                        TextEntry::make('current_subscription')
                            ->label('Current Contribution')
                            ->helperText(function ($record) {
                                $subscription = UserSubscriptionService::getActiveSubscription($record);
                                if ($subscription) {
                                    try {
                                        $stripeSubscription = $subscription->asStripeSubscription();
                                        $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('n/j/Y');
                                        return sprintf('Next bill %s', $nextBillingDate);
                                    } catch (\Exception $e) {
                                        return null;
                                    }
                                }
                                return null;
                            })
                            ->state(function ($record) {
                                $subscription = UserSubscriptionService::getActiveSubscription($record);

                                if ($subscription) {
                                    try {
                                        // Get the Stripe subscription object with pricing info
                                        $stripeSubscription = $subscription->asStripeSubscription();
                                        $firstItem = $stripeSubscription->items->data[0];
                                        $price = $firstItem->price;
                                        $baseAmount = \Brick\Money\Money::ofMinor($price->unit_amount, 'USD');

                                        // Check if user has fee coverage and show total instead
                                        $hasFeesCovered = count($stripeSubscription->items->data) > 1;
                                        if ($hasFeesCovered) {
                                            // Calculate total cost including fees
                                            $totalAmount = collect($stripeSubscription->items->data)
                                                ->sum(fn($item) => $item->price->unit_amount);
                                            $totalCost = \Brick\Money\Money::ofMinor($totalAmount, 'USD');

                                            return sprintf(
                                                '%s/%s',
                                                $totalCost->formatTo('en_US'),
                                                $price->recurring->interval
                                            );
                                        }

                                        return sprintf(
                                            '%s/%s',
                                            $baseAmount->formatTo('en_US'),
                                            $price->recurring->interval
                                        );
                                    } catch (\Exception $e) {
                                        Log::warning('Failed to retrieve subscription display info', [
                                            'subscription_id' => $subscription->id,
                                            'error' => $e->getMessage()
                                        ]);
                                        return 'Amount unavailable';
                                    }
                                }

                                return 'No active contribution';
                            })
                            ->size('lg')
                            ->weight('bold')
                            ->iconPosition(IconPosition::After)
                            ->iconColor('danger')
                            ->icon(function ($record) {
                                $hasFeeCovered = count($record->subscription('default')->items) > 1;
                                return $hasFeeCovered ? 'tabler-heart-dollar' : null;
                            }),
                        TextEntry::make('rehearsal_hours')
                            ->label('Rehearsal Hours')
                            ->helperText(function ($record) {
                                $changeInfo = static::getBenefitChangeInfo($record);

                                if ($changeInfo) {
                                    $subscription = UserSubscriptionService::getActiveSubscription($record);
                                    $nextBillingDate = 'N/A';

                                    if ($subscription) {
                                        try {
                                            $stripeSubscription = $subscription->asStripeSubscription();
                                            $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('n/j/Y');
                                        } catch (\Exception $e) {
                                            // Fall back to N/A
                                        }
                                    }

                                    return sprintf(
                                        '%d hours on %s',
                                        $changeInfo['next_month'],
                                        $nextBillingDate
                                    );
                                }

                                return null;
                            })
                            ->state(function ($record) {
                                $remaining = $record->getRemainingFreeHours();
                                $total = \App\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($record);
                                return sprintf('%g / %g hours', $remaining, $total);
                            })
                            ->size('lg')
                            ->weight('bold'),
                        Flex::make([
                            OpenBillingPortalAction::make(),
                            ModifyMembershipAmountAction::make(),
                        ])->columnSpanFull()
                    ]),

                // Cancelled sustaining membership section
                Section::make('Cancelled Sustaining Membership')
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
                        TextEntry::make('cancellation_info')
                            ->label('Contribution Status')
                            ->state(function ($record) {
                                $subscription = static::getActiveSubscription($record);
                                if ($subscription && $subscription->ends_at) {
                                    // Get price from Stripe to display amount
                                    $price = \Laravel\Cashier\Cashier::stripe()->prices->retrieve($subscription->stripe_price);
                                    $amount = \Brick\Money\Money::ofMinor($price->unit_amount, 'USD');

                                    return sprintf(
                                        '%s/%s contribution - Ends %s',
                                        $amount->formatTo('en_US'),
                                        $price->recurring->interval,
                                        $subscription->ends_at->diffForHumans()
                                    );
                                }
                                return 'Contribution cancelled';
                            })
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('benefits_until_end')
                            ->label('Benefits Until Cancellation')
                            ->state(function ($record) {
                                $subscription = static::getActiveSubscription($record);
                                if ($subscription && $subscription->ends_at) {
                                    $totalHours = \App\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($record);
                                    return sprintf('You retain %d free hours/month until %s', $totalHours, $subscription->ends_at->format('M j, Y'));
                                }
                                return 'Benefits ended';
                            })
                            ->badge()
                            ->color('info'),
                        TextEntry::make('remaining_free_hours')
                            ->label('Free Hours Remaining This Month')
                            ->state(function ($record) {
                                $remaining = $record->getRemainingFreeHours();
                                $used = $record->getUsedFreeHoursThisMonth();
                                return sprintf('%g hours (%g used)', $remaining, $used);
                            })
                            ->badge()
                            ->color(function ($record) {
                                $remaining = $record->getRemainingFreeHours();
                                return $remaining > 2 ? 'success' : ($remaining > 0 ? 'warning' : 'danger');
                            }),
                        TextEntry::make('resume_benefits')
                            ->label('Resume Benefits')
                            ->state('Resume your contribution to continue supporting the collective and receiving member benefits')
                            ->helperText('Your contribution history and member standing will be preserved'),
                        Flex::make([
                            ResumeMembershipAction::make(),
                            OpenBillingPortalAction::make(),
                        ])->columnSpanFull()
                    ]),

            ]);
    }

    /**
     * Check if user's benefits will change next month and return change info
     */
    private static function getBenefitChangeInfo($record): ?array
    {
        $subscription = UserSubscriptionService::getActiveSubscription($record);
        if (!$subscription) {
            return null;
        }

        try {
            $currentHours = \App\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($record);

            $stripeSubscription = $subscription->asStripeSubscription();
            $firstItem = $stripeSubscription->items->data[0];
            $nextMonthAmount = $firstItem->price->unit_amount / 100;
            $nextMonthHours = $nextMonthAmount >= 50 ? 6 : ($nextMonthAmount >= 25 ? 5 : 4);

            if ($currentHours === $nextMonthHours) {
                return null; // No change
            }

            return [
                'current' => $currentHours,
                'next_month' => $nextMonthHours,
                'is_increase' => $nextMonthHours > $currentHours
            ];
        } catch (\Exception $e) {
            return null;
        }
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

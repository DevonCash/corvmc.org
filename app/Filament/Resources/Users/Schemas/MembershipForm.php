<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Resources\Users\Actions\CancelMembershipAction;
use App\Filament\Resources\Users\Actions\CreateMembershipSubscriptionAction;
use App\Filament\Resources\Users\Actions\ModifyMembershipAmountAction;
use App\Filament\Resources\Users\Actions\OpenBillingPortalAction;
use App\Models\User;
use App\Services\UserSubscriptionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;

class MembershipForm
{
    public static function configure($schema)
    {
        return $schema
            ->schema([

                // Show the most relevant section based on user's membership status
                Section::make('Membership')
                    ->visible(fn($record) => !$record?->subscription('default')?->active())
                    ->description('Support our community with a sliding scale membership that fits your budget.')
                    ->visible(fn($record) => $record->subscription('default') === null)
                    ->headerActions([
                        CreateMembershipSubscriptionAction::make()
                    ]),

                Section::make('Membership')
                    // ->visible(fn($record) => $record?->subscription('default')?->active())
                    ->description('Your membership is active! You can change your amount or access billing details below.')
                    ->columns(2)
                    ->visible(fn($record) => $record->subscription('default'))
                    ->headerActions([])->schema([
                        TextEntry::make('remaining_free_hours')
                            ->label('Free Hours Remaining This Month')
                            ->state(function ($record) {
                                $service = \UserSubscriptionService::getFacadeRoot();
                                $totalHours = $service->getUserMonthlyFreeHours($record);
                                return $record->getRemainingFreeHours() . ' / ' . $totalHours . ' hours';
                            }),
                        TextEntry::make('current_subscription')
                            ->label('Current Subscription')
                            ->state(function ($record) {
                                $service = \UserSubscriptionService::getFacadeRoot();
                                $displayInfo = $service->getSubscriptionDisplayInfo($record);

                                if ($displayInfo['has_subscription']) {
                                    return sprintf(
                                        '%s/%s - %s',
                                        $displayInfo['formatted_amount'],
                                        $displayInfo['interval'],
                                        $displayInfo['status']
                                    );
                                }

                                return 'No active subscription';
                            })
                            ->badge()
                            ->color('success'),
                        Flex::make([
                            OpenBillingPortalAction::make(),
                            ModifyMembershipAmountAction::make(),
                            CancelMembershipAction::make(),
                        ])->columnSpanFull()
                    ]),

            ]);
    }
}

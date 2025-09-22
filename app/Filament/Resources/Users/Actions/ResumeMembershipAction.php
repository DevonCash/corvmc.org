<?php

namespace App\Filament\Resources\Users\Actions;

use App\Facades\UserSubscriptionService;
use Filament\Actions\Action;

class ResumeMembershipAction
{
    public static function make(): Action
    {
        return Action::make('resume_membership')
            ->label('Resume Contribution')
            ->icon('heroicon-o-arrow-path')
            ->outlined()
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Resume Contribution')
            ->modalDescription('Are you sure you want to resume your contribution? Your contribution will continue until you cancel it again.')
            ->modalSubmitActionLabel('Yes, Resume Contribution')
            ->action(function ($record) {
                $result = UserSubscriptionService::resumeSubscription($record);

                if ($result['success']) {
                    \Filament\Notifications\Notification::make()
                        ->title('Contribution Resumed')
                        ->body($result['message'])
                        ->success()
                        ->send();
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('Failed to resume contribution')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                }
            });
    }
}
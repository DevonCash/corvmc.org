<?php

namespace App\Filament\Staff\Resources\Users\Actions;

use App\Models\User;
use CorvMC\Finance\Facades\SubscriptionService;
use Filament\Actions\Action;

class ResumeMembershipAction
{
    public static function make(): Action
    {
        return Action::make('resumeMembershipAction')
            ->label('Resume Contribution')
            ->icon('tabler-restore')
            ->outlined()
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Resume Contribution')
            ->modalDescription('Are you sure you want to resume your contribution? Your contribution will continue until you cancel it again.')
            ->modalSubmitActionLabel('Yes, Resume Contribution')
            ->action(function () {
                SubscriptionService::resume(User::me());

                \Filament\Notifications\Notification::make()
                    ->title('Contribution Resumed')
                    ->body('Your contribution has been resumed successfully.')
                    ->success()
                    ->send();
            });
    }
}

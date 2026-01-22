<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
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
                \CorvMC\Finance\Actions\Subscriptions\ResumeSubscription::run(User::me());

                \Filament\Notifications\Notification::make()
                    ->title('Contribution Resumed')
                    ->body('Your contribution has been resumed successfully.')
                    ->success()
                    ->send();
            });
    }
}

<?php

namespace App\Filament\Resources\Users\Actions;

use Filament\Actions\Action;

class ResumeMembershipAction
{
    public static function make(): Action
    {
        return Action::make('resume_membership')
            ->label('Resume Contribution')
            ->icon('tabler-restore')
            ->outlined()
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Resume Contribution')
            ->modalDescription('Are you sure you want to resume your contribution? Your contribution will continue until you cancel it again.')
            ->modalSubmitActionLabel('Yes, Resume Contribution')
            ->action(function ($record) {
                try {
                    \App\Actions\Subscriptions\ResumeSubscription::run($record);

                    \Filament\Notifications\Notification::make()
                        ->title('Contribution Resumed')
                        ->body('Your contribution has been resumed successfully.')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    \Filament\Notifications\Notification::make()
                        ->title('Failed to resume contribution')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}

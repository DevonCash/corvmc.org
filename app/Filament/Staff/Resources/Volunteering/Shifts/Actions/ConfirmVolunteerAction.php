<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\Interested;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ConfirmVolunteerAction
{
    public static function make(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon('tabler-check')
            ->color('success')
            ->size('sm')
            ->requiresConfirmation()
            ->visible(fn (HourLog $record) => $record->status instanceof Interested)
            ->authorize(fn (HourLog $record) => auth()->user()->can('confirm', $record))
            ->action(function (HourLog $record) {
                app(HourLogService::class)->confirm($record, auth()->user());
                Notification::make()->title('Volunteer confirmed')->success()->send();
            });
    }
}

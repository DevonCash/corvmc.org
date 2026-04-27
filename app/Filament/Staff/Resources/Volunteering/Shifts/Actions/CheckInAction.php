<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CheckInAction
{
    public static function make(): Action
    {
        return Action::make('checkIn')
            ->label('Check In')
            ->icon('tabler-login')
            ->color('info')
            ->size('sm')
            ->visible(fn (HourLog $record) => $record->status instanceof Confirmed)
            ->authorize(fn (HourLog $record) => auth()->user()->can('checkIn', $record))
            ->action(function (HourLog $record) {
                app(HourLogService::class)->checkIn($record);
                Notification::make()->title('Volunteer checked in')->success()->send();
            });
    }
}

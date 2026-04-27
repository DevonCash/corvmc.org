<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CheckOutAction
{
    public static function make(): Action
    {
        return Action::make('checkOut')
            ->label('Check Out')
            ->icon('tabler-logout')
            ->color('warning')
            ->size('sm')
            ->visible(fn (HourLog $record) => $record->status instanceof CheckedIn)
            ->authorize(fn (HourLog $record) => auth()->user()->can('checkOut', $record))
            ->action(function (HourLog $record) {
                app(HourLogService::class)->checkOut($record);
                Notification::make()->title('Volunteer checked out')->success()->send();
            });
    }
}

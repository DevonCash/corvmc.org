<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ReleaseVolunteerAction
{
    public static function make(): Action
    {
        return Action::make('release')
            ->label('Release')
            ->icon('tabler-x')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalDescription('This will remove the volunteer from this shift.')
            ->visible(fn (HourLog $record) => $record->status instanceof Interested
                || $record->status instanceof Confirmed
                || $record->status instanceof CheckedIn)
            ->authorize(fn (HourLog $record) => auth()->user()->can('release', $record))
            ->action(function (HourLog $record) {
                app(HourLogService::class)->release($record, auth()->user());
                Notification::make()->title('Volunteer released')->success()->send();
            });
    }
}

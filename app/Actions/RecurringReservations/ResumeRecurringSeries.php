<?php

namespace App\Actions\RecurringReservations;

use CorvMC\Support\Enums\RecurringSeriesStatus;
use App\Filament\Actions\Action;
use Lorisleiva\Actions\Concerns\AsAction;

class ResumeRecurringSeries
{
    use AsAction;
    public function handle($recurringSeries)
    {
        // Update the recurring series with the provided data
        $recurringSeries->update(['status' => RecurringSeriesStatus::ACTIVE]);

        // Additional logic can be added here if needed

        return $recurringSeries;
    }

    public static function filamentAction(): Action
    {
        return Action::make('resumeRecurringSeries')
            ->icon('tabler-play')
            ->label('Resume')
            ->action(function ($record) {
               return static::run($record);
            });
    }
}

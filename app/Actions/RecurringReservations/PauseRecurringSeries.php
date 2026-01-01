<?php

namespace App\Actions\RecurringReservations;

use App\Enums\RecurringSeriesStatus;
use App\Filament\Actions\Action;
use App\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

class PauseRecurringSeries
{
    use AsAction;
    public function handle($recurringSeries)
    {
        $recurringSeries->update(['status' => RecurringSeriesStatus::PAUSED]);

        return $recurringSeries;
    }

    public static function filamentAction(): Action
    {
        return Action::make('pauseRecurringSeries')
            ->icon('tabler-pause')
            ->label('Pause')
            ->action(function ($record) {
                return static::run($record);
            });
    }
}

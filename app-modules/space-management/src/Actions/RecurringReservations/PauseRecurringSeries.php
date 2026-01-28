<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action\Action;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;
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

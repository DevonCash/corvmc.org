<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Events\RecurringSeriesResumed;
use App\Filament\Shared\Actions\Action\Action;
use Lorisleiva\Actions\Concerns\AsAction;

class ResumeRecurringSeries
{
    use AsAction;
    public function handle($recurringSeries)
    {
        $recurringSeries->update(['status' => RecurringSeriesStatus::ACTIVE]);

        RecurringSeriesResumed::dispatch($recurringSeries);

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

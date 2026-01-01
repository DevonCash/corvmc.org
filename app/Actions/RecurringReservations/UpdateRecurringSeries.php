<?php

namespace App\Actions\RecurringReservations;

use App\Filament\Actions\Action;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateRecurringSeries
{
    use AsAction;

    public function handle($recurringSeries, $data)
    {
        $recurringSeries->update($data);

        return $recurringSeries;
    }

    static function filamentAction(): Action
    {
        // TODO: Add schema
        return Action::make('updateRecurringSeries')
            ->label('Update Recurring Series')
            ->slideOver(true)
            ->action(function ($record, $data) {
                $action = new self();
                return $action->handle($record, $data);
            });
    }
}

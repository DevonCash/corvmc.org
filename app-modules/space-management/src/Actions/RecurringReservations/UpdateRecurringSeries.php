<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action\Action;
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

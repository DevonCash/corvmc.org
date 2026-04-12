<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action\Action;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::updateRecurringSeries() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class UpdateRecurringSeries
{
    use AsAction;

    public function handle(RecurringSeries $recurringSeries, array $data): RecurringSeries
    {
        return app(RecurringReservationService::class)->updateRecurringSeries($recurringSeries, $data);
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

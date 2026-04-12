<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action\Action;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::pauseRecurringSeries() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class PauseRecurringSeries
{
    use AsAction;
    
    public function handle(RecurringSeries $recurringSeries, ?Carbon $pauseUntil = null): RecurringSeries
    {
        return app(RecurringReservationService::class)->pauseRecurringSeries($recurringSeries, $pauseUntil);
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

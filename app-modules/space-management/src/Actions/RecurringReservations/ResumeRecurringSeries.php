<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action\Action;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;

/**
 * @deprecated Use RecurringReservationService::resumeRecurringSeries() instead.
 * This action will be removed in a future version.
 * 
 * The business logic has been moved to RecurringReservationService for better
 * organization and testability. This action now delegates to the service.
 */
class ResumeRecurringSeries
{
    
    public function handle(RecurringSeries $recurringSeries): RecurringSeries
    {
        return app(RecurringReservationService::class)->resumeRecurringSeries($recurringSeries);
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

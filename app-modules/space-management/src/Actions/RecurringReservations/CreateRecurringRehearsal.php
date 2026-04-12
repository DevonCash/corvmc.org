<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action;
use App\Filament\Staff\Resources\RecurringReservations\Schemas\RecurringReservationForm;
use CorvMC\SpaceManagement\Services\RecurringReservationService;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use RecurringReservationService::createRecurringRehearsal() instead
 * This action is maintained for backward compatibility only.
 * New code should use the RecurringReservationService directly.
 */
class CreateRecurringRehearsal
{
    use AsAction;

    /**
     * @deprecated Use RecurringReservationService::createRecurringRehearsal() instead
     */
    public function handle(array $data): RecurringSeries
    {
        return app(RecurringReservationService::class)->createRecurringRehearsal($data);
    }

    public static function filamentAction(): Action
    {
        return Action::make('createRecurringSeries')
            ->label('Create')
            ->icon('tabler-calendar-repeat')
            ->slideOver(true)
            ->schema(fn($schema) => RecurringReservationForm::configure($schema))
            ->action(fn($data) => static::run($data));
    }
}

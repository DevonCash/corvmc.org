<?php

namespace CorvMC\SpaceManagement\Actions\RecurringReservations;

use App\Filament\Shared\Actions\Action;
use App\Filament\Staff\Resources\RecurringReservations\Schemas\RecurringReservationForm;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Support\Actions\GenerateRecurringInstances;
use CorvMC\Support\Enums\RecurringSeriesStatus;
use CorvMC\Support\Models\RecurringSeries;
use Lorisleiva\Actions\Concerns\AsAction;
use RRule\RRule;

class CreateRecurringRehearsal
{
    use AsAction;

    /**
     * Create a new recurring rehearsal series.
     *
     * @throws \InvalidArgumentException If user is not a sustaining member
     */
    public function handle(array $data): RecurringSeries
    {
        $series = RecurringSeries::create([
            'user_id' => $data['user_id'],
            'recurable_type' => 'rehearsal_reservation',
            'recurrence_rule' => new RRule([
                'FREQ' => $data['frequency'],
                'INTERVAL' => $data['interval'],
                'BYDAY' => $data['byday'] ?? null,
            ]),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'series_start_date' => $data['series_start_date'],
            'series_end_date' => $data['series_end_date'] ?? null,
            'max_advance_days' => $data['max_advance_days'] ?? 90,
            'status' => RecurringSeriesStatus::ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);

        // Generate initial instances
        GenerateRecurringInstances::run($series);

        return $series->fresh();
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

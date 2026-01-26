<?php

namespace CorvMC\Finance\Actions\Payments;

use App\Filament\Shared\Actions\Action;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsComped
{
    use AsAction;

    public function handle(RehearsalReservation $reservation, ?string $notes = null): void
    {
        $reservation->charge?->markAsComped($notes);
    }

    public static function filamentAction(): Action
    {
        return Action::make('mark_comped')
            ->label('Comp')
            ->icon('tabler-gift')
            ->color('info')
            ->authorize('manage')
            ->visible(fn (RehearsalReservation $record) => $record->needsPayment())
            ->schema([
                Textarea::make('comp_reason')
                    ->label('Comp Reason')
                    ->placeholder('Why is this reservation being comped?')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (RehearsalReservation $record, array $data) {
                static::run($record, $data['comp_reason']);

                \Filament\Notifications\Notification::make()
                    ->title('Reservation comped')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  Collection<int, RehearsalReservation>  $records
     */
    public static function filamentBulkAction(): Action
    {
        return Action::make('mark_comped_bulk')
            ->label('Comp Reservations')
            ->icon('tabler-gift')
            ->color('info')
            ->authorize('manage')
            ->schema([
                Textarea::make('comp_reason')
                    ->label('Comp Reason')
                    ->placeholder('Why are these reservations being comped?')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (Collection $records, array $data) {
                $count = 0;
                foreach ($records as $record) {
                    if ($record instanceof RehearsalReservation && $record->needsPayment()) {
                        static::run($record, $data['comp_reason']);
                        $count++;
                    }
                }
            })
            ->successNotificationTitle('Reservations comped')
            ->successNotification(fn (Collection $records) => $records->filter(fn ($r) => $r instanceof RehearsalReservation && $r->needsPayment())->count().' reservations marked as comped');
    }
}

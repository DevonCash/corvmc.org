<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\ConfirmReservation;
use App\Concerns\AsFilamentAction;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsComped
{
    use AsAction, AsFilamentAction;

    protected static ?string $actionLabel = 'Comp';

    protected static ?string $actionIcon = 'tabler-gift';

    protected static string $actionColor = 'info';

    protected static string $actionSuccessMessage = 'Reservation comped';

    protected static function isActionVisible(...$args): bool
    {
        $record = $args[0] ?? null;

        return $record instanceof RehearsalReservation &&
            ! $record->cost->isZero() &&
            $record->isUnpaid() &&
            User::me()->can('manage reservations');
    }

    public function handle(Reservation $reservation, ?string $notes = null): void
    {
        // If the reservation is pending, confirm it first
        if ($reservation instanceof RehearsalReservation && $reservation->status === 'pending') {
            $reservation = ConfirmReservation::run($reservation);
            $reservation->refresh();
        }

        $reservation->update([
            'payment_status' => 'comped',
            'payment_method' => 'comp',
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }

    public static function filamentAction(): Action
    {
        return Action::make('mark_comped')
            ->label('Comp')
            ->icon('tabler-gift')
            ->color('info')
            ->visible(fn (Reservation $record) => $record instanceof RehearsalReservation &&
                ! $record->cost->isZero() &&
                $record->isUnpaid() &&
                User::me()->can('manage reservations'))
            ->schema([
                Textarea::make('comp_reason')
                    ->label('Comp Reason')
                    ->placeholder('Why is this reservation being comped?')
                    ->required()
                    ->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                static::run($record, $data['comp_reason']);

                \Filament\Notifications\Notification::make()
                    ->title('Reservation comped')
                    ->success()
                    ->send();
            });
    }

    public static function filamentBulkAction(): Action
    {
        return Action::make('mark_comped_bulk')
            ->label('Comp Reservations')
            ->icon('tabler-gift')
            ->color('info')
            ->visible(fn () => User::me()->can('manage reservations'))
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
                    if ($record->cost->isPositive() && $record->isUnpaid()) {
                        static::run($record, $data['comp_reason']);
                        $count++;
                    }
                }
            })
            ->successNotificationTitle('Reservations comped')
            ->successNotification(fn (Collection $records) => $records->filter(fn ($r) => $r->cost->isPositive() && $r->isUnpaid())->count().' reservations marked as comped');
    }
}

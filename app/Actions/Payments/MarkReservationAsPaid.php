<?php

namespace App\Actions\Payments;

use App\Actions\Reservations\ConfirmReservation;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\RehearsalReservation;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsPaid
{
    use AsAction;

    public function handle(Reservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        // If the reservation is pending, confirm it first
        if ($reservation instanceof RehearsalReservation && $reservation->status === ReservationStatus::Pending) {
            $reservation = ConfirmReservation::run($reservation);
            $reservation->refresh();
        }

        $reservation->update([
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => $paymentMethod,
            'paid_at' => now(),
            'payment_notes' => $notes,
        ]);
    }

    public static function filamentAction(): Action
    {
        return Action::make('mark_paid')
            ->label('Mark Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->visible(fn (Reservation $record) => $record instanceof RehearsalReservation &&
                ! $record->cost->isZero() &&
                $record->isUnpaid() &&
                User::me()->can('manage reservations'))
            ->schema([
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Credit/Debit Card',
                        'venmo' => 'Venmo',
                        'paypal' => 'PayPal',
                        'zelle' => 'Zelle',
                        'check' => 'Check',
                        'other' => 'Other',
                    ])
                    ->required(),
                Textarea::make('payment_notes')
                    ->label('Payment Notes')
                    ->placeholder('Optional notes about the payment...')
                    ->rows(2),
            ])
            ->action(function (Reservation $record, array $data) {
                static::run($record, $data['payment_method'], $data['payment_notes'] ?? null);

                \Filament\Notifications\Notification::make()
                    ->title('Payment recorded')
                    ->success()
                    ->send();
            });
    }

    public static function filamentBulkAction(): Action
    {
        return Action::make('mark_paid_bulk')
            ->label('Mark as Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->visible(fn () => User::me()->can('manage reservations'))
            ->schema([
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Credit/Debit Card',
                        'venmo' => 'Venmo',
                        'paypal' => 'PayPal',
                        'zelle' => 'Zelle',
                        'check' => 'Check',
                        'other' => 'Other',
                    ])
                    ->required(),
                Textarea::make('payment_notes')
                    ->label('Payment Notes')
                    ->placeholder('Optional notes about the payment...')
                    ->rows(2),
            ])
            ->action(function (Collection $records, array $data) {
                $count = 0;
                foreach ($records as $record) {
                    if ($record->cost->isPositive() && $record->isUnpaid()) {
                        static::run($record, $data['payment_method'], $data['payment_notes'] ?? null);
                        $count++;
                    }
                }
            })
            ->successNotificationTitle('Payments recorded')
            ->successNotification(fn (Collection $records, array $data) => $records->filter(fn ($r) => $r->cost->isPositive() && $r->isUnpaid())->count().' reservations marked as paid');
    }
}

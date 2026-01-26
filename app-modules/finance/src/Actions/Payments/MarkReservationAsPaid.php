<?php

namespace CorvMC\Finance\Actions\Payments;

use CorvMC\SpaceManagement\Actions\Reservations\ConfirmReservation;
use App\Filament\Shared\Actions\Action;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReservationAsPaid
{
    use AsAction;

    public function handle(RehearsalReservation $reservation, ?string $paymentMethod = null, ?string $notes = null): void
    {
        // If the reservation is scheduled or reserved, confirm it first
        if (in_array($reservation->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
            $reservation = ConfirmReservation::run($reservation);
            $reservation->refresh();
        }

        // Update charge record
        $reservation->charge?->markAsPaid($paymentMethod ?? 'manual', null, $notes);
    }

    public static function filamentAction(): Action
    {
        return Action::make('mark_paid')
            ->label('Mark Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->authorize('manage')
            ->visible(fn (RehearsalReservation $record) => $record->needsPayment())
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
            ->action(function (RehearsalReservation $record, array $data) {
                static::run($record, $data['payment_method'], $data['payment_notes'] ?? null);

                \Filament\Notifications\Notification::make()
                    ->title('Payment recorded')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  Collection<int, RehearsalReservation>  $records
     */
    public static function filamentBulkAction(): Action
    {
        return Action::make('mark_paid_bulk')
            ->label('Mark as Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->authorize('manage')
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
                    if ($record instanceof RehearsalReservation && $record->needsPayment()) {
                        static::run($record, $data['payment_method'], $data['payment_notes'] ?? null);
                        $count++;
                    }
                }
            })
            ->successNotificationTitle('Payments recorded')
            ->successNotification(fn (Collection $records, array $data) => $records->filter(fn ($r) => $r instanceof RehearsalReservation && $r->needsPayment())->count().' reservations marked as paid');
    }
}

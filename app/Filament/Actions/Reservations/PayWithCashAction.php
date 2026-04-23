<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PayWithCashAction
{
    public static function make(): Action
    {
        return Action::make('pay_cash')
            ->label('Pay with Cash')
            ->icon('tabler-cash')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('You\'ll need to pay in cash at the space before your reservation. Your booking will be confirmed now.')
            ->visible(fn (RehearsalReservation $record) => static::canPay($record))
            ->action(function (RehearsalReservation $record) {
                $user = $record->getResponsibleUser();
                $lineItems = Finance::price([$record], $user);
                $totalCents = (int) $lineItems->sum('amount');

                $order = Order::create([
                    'user_id' => $user->id,
                    'total_amount' => 0,
                ]);

                foreach ($lineItems as $lineItem) {
                    $lineItem->order_id = $order->id;
                    $lineItem->save();
                }

                $order->update(['total_amount' => $totalCents]);

                Finance::commit($order->fresh(), ['cash' => $totalCents]);
                $record->status->transitionTo(Confirmed::class);

                Notification::make()
                    ->title('Reservation confirmed')
                    ->body('Please bring ' . $order->formattedTotal() . ' in cash to the space.')
                    ->success()
                    ->send();
            });
    }

    private static function canPay(RehearsalReservation $record): bool
    {
        if (! ($record->status instanceof Scheduled)) {
            return false;
        }

        if ($record->reserved_at->gt(now()->addWeek())) {
            return false;
        }

        $existingOrder = Finance::findActiveOrder($record);

        return ! $existingOrder;
    }
}

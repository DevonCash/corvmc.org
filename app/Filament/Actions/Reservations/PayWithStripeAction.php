<?php

namespace App\Filament\Actions\Reservations;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PayWithStripeAction
{
    public static function make(): Action
    {
        return Action::make('pay_stripe')
            ->label('Pay Online')
            ->icon('tabler-credit-card')
            ->color('success')
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

                $committed = Finance::commit($order->fresh(), ['stripe' => $totalCents]);
                $record->status->transitionTo(Confirmed::class);

                $checkoutUrl = $committed->checkoutUrl();
                if ($checkoutUrl) {
                    return redirect($checkoutUrl);
                }

                Notification::make()
                    ->title('Payment session created')
                    ->success()
                    ->send();
            });
    }

    private static function canPay(RehearsalReservation $record): bool
    {
        // Must be Scheduled
        if (! ($record->status instanceof Scheduled)) {
            return false;
        }

        // Must be within the confirmation window
        if ($record->reserved_at->gt(now()->addWeek())) {
            return false;
        }

        // Must not already have an active Order
        $existingOrder = Finance::findActiveOrder($record);
        if ($existingOrder) {
            return false;
        }

        return true;
    }
}

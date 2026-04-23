<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Member\Resources\Reservations\ReservationResource;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function handleRecordCreation(array $data): Reservation
    {
        $user = auth()->user();
        $startTime = Carbon::parse($data['reserved_at']);
        $endTime = Carbon::parse($data['reserved_until']);

        // Always start as Scheduled — confirmation happens after payment method is chosen
        return RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'notes' => $data['notes'] ?? null,
            'status' => Scheduled::class,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
            'hours_used' => $startTime->diffInMinutes($endTime) / 60,
        ]);
    }

    protected function afterCreate(): void
    {
        /** @var RehearsalReservation $reservation */
        $reservation = $this->record;
        $user = auth()->user();
        $data = $this->data;

        // Only create an Order if within the confirmation window
        if (! $this->isWithinConfirmationWindow()) {
            return; // Path 3: stays Scheduled, no Order
        }

        try {
            // Price the reservation
            $lineItems = Finance::price([$reservation], $user);
            $totalCents = (int) $lineItems->sum('amount');

            // Create the Order
            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => 0,
            ]);

            foreach ($lineItems as $lineItem) {
                $lineItem->order_id = $order->id;
                $lineItem->save();
            }

            $order->update(['total_amount' => $totalCents]);

            if ($totalCents <= 0) {
                // Path 2: fully discounted — commit with no rails, auto-completes
                Finance::commit($order->fresh(), []);
                $reservation->status->transitionTo(Confirmed::class);

                return;
            }

            // Path 1: has a cost — commit with chosen payment rail
            $paymentMethod = $data['payment_method'] ?? 'stripe';
            $committed = Finance::commit($order->fresh(), [$paymentMethod => $totalCents]);

            // Confirm the reservation (it now has a pending payment)
            $reservation->status->transitionTo(Confirmed::class);

            if ($paymentMethod === 'stripe') {
                $checkoutUrl = $committed->checkoutUrl();
                if ($checkoutUrl) {
                    session()->put('stripe_checkout_url', $checkoutUrl);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to create order for reservation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Payment Error')
                ->body('Your reservation was created but we couldn\'t set up payment: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($checkoutUrl = session()->pull('stripe_checkout_url')) {
            return $checkoutUrl;
        }

        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    private function isWithinConfirmationWindow(): bool
    {
        $reservationDate = $this->record->reserved_at;

        return $reservationDate->lte(now()->addWeek());
    }
}

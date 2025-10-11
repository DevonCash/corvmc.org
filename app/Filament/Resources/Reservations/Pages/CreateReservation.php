<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function afterCreate(): void
    {
        /** @var Reservation $record */
        $record = $this->record;

        // Check if this should go to checkout
        $shouldCheckout = $this->shouldRedirectToCheckout($record);

        if ($shouldCheckout) {
            try {
                $reservationService = app(ReservationService::class);
                $session = $reservationService->createCheckoutSession($record);

                // Store the redirect URL in session to use after the page redirects
                session()->put('stripe_checkout_url', $session->url);
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Payment Error')
                    ->body('Unable to create payment session: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        // Check if we have a Stripe checkout URL to redirect to
        if ($checkoutUrl = session()->pull('stripe_checkout_url')) {
            return $checkoutUrl;
        }

        // Otherwise, redirect to the reservation view page
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function shouldRedirectToCheckout(Reservation $record): bool
    {
        // Must have a positive cost
        if ($record->cost->isZero() || $record->cost->isNegative()) {
            return false;
        }

        // Must be unpaid
        if (!$record->isUnpaid()) {
            return false;
        }

        // Cannot be recurring
        if ($record->recurring_reservation_id) {
            return false;
        }

        // Must be within auto-confirm range (next 7 days)
        // $record->reserved_at is already a Carbon instance from the model cast
        $reservationDate = $record->reserved_at;
        $oneWeekFromNow = now()->addWeek();

        return $reservationDate->lte($oneWeekFromNow);
    }
}

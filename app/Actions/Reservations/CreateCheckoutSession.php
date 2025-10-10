<?php

namespace App\Actions\Reservations;

use App\Models\RehearsalReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCheckoutSession
{
    use AsAction;

    public const MINUTES_PER_BLOCK = 30; // Practice space credits are in 30-minute blocks

    /**
     * Create a Stripe checkout session for a reservation payment.
     *
     * @throws \Exception If price not configured or no payment required
     */
    public function handle(RehearsalReservation $reservation)
    {
        $user = $reservation->user;

        // Ensure user has a Stripe customer ID
        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        $priceId = config('services.stripe.practice_space_price_id');

        if (!$priceId) {
            throw new \Exception('Practice space price not configured. Run: php artisan practice-space:create-price');
        }

        // Calculate paid hours and convert to 30-minute blocks
        $paidHours = $reservation->hours_used - $reservation->free_hours_used;
        $paidBlocks = $this->hoursToBlocks($paidHours);

        if ($paidBlocks <= 0) {
            throw new \Exception('No payment required for this reservation.');
        }

        // Use Cashier's checkout method
        $checkout = $user->checkout([
            $priceId => $paidBlocks,
        ], [
            'success_url' => route('checkout.success') . '?session_id={CHECKOUT_SESSION_ID}&user_id=' . $reservation->getResponsibleUser()->id,
            'cancel_url' => route('checkout.cancel') . '?user_id=' . $reservation->getResponsibleUser()->id . '&type=practice_space_reservation',
            'metadata' => [
                'reservation_id' => $reservation->id,
                'user_id' => $user->id,
                'type' => 'practice_space_reservation',
                'free_hours_used' => $reservation->free_hours_used,
            ],
        ]);

        return $checkout;
    }

    /**
     * Convert hours to blocks for credit system.
     */
    protected function hoursToBlocks(float $hours): int
    {
        return (int) ceil(($hours * 60) / self::MINUTES_PER_BLOCK);
    }
}

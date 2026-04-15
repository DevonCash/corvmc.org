<?php

namespace App\Listeners;

use CorvMC\Finance\Facades\CreditService;
use CorvMC\Finance\Facades\PaymentService;
use CorvMC\Finance\Facades\PricingService;
use CorvMC\SpaceManagement\Events\ReservationConfirmed;
use CorvMC\SpaceManagement\Events\ReservationCreated;
use CorvMC\SpaceManagement\Events\ReservationCancelled;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Handles financial aspects of reservations through event-driven integration.
 * 
 * This listener acts as the integration point between SpaceManagement and Finance modules,
 * ensuring proper separation of concerns while maintaining business logic coherence.
 */
class HandleReservationFinance
{
    /**
     * Handle reservation creation - calculate pricing and apply credits.
     */
    public function handleReservationCreated(ReservationCreated $event): void
    {
        $reservation = $event->chargeable;
        
        if (!$reservation instanceof RehearsalReservation) {
            return;
        }

        // Calculate pricing using Finance module
        $pricing = PricingService::calculatePriceForUser($reservation);
        
        // Store pricing information on the reservation
        // This would typically update the charge record
        if ($reservation->charge) {
            $reservation->charge->update([
                'amount' => $pricing->amount,
                'net_amount' => $pricing->net_amount,
                'credits_applied' => $pricing->credits_applied,
            ]);
        }
    }

    /**
     * Handle reservation confirmation - deduct credits if applicable.
     */
    public function handleReservationConfirmed(ReservationConfirmed $event): void
    {
        $reservation = $event->chargeable;
        
        if (!$reservation instanceof RehearsalReservation) {
            return;
        }

        // Apply credits for free hours used
        if ($reservation->free_hours_used > 0) {
            $user = $reservation->getResponsibleUser();
            
            if ($user && $user->isSustainingMember()) {
                CreditService::deductCredits(
                    $user,
                    $reservation->free_hours_used,
                    'practice_hours',
                    "Used for reservation #{$reservation->id}"
                );
            }
        }
    }

    /**
     * Handle reservation cancellation - restore credits if applicable.
     */
    public function handleReservationCancelled(ReservationCancelled $event): void
    {
        $reservation = $event->chargeable;
        
        if (!$reservation instanceof RehearsalReservation) {
            return;
        }

        // Restore credits if they were used
        if ($reservation->free_hours_used > 0) {
            $user = $reservation->getResponsibleUser();
            
            if ($user) {
                CreditService::addCredits(
                    $user,
                    $reservation->free_hours_used,
                    'practice_hours',
                    "Restored from cancelled reservation #{$reservation->id}"
                );
            }
        }
    }
}
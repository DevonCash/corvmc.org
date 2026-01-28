<?php

namespace CorvMC\Finance\Contracts;

use App\Models\User;

/**
 * Interface for models that can be priced and charged.
 *
 * Any model implementing this interface can be attached to a Charge record
 * for pricing, credits, and payment tracking.
 *
 * Examples: RehearsalReservation, EquipmentLoan, EventTicket
 */
interface Chargeable
{
    /**
     * Get the billable units for pricing calculation.
     *
     * For reservations: hours
     * For equipment: days or quantity
     * For tickets: count
     */
    public function getBillableUnits(): float;

    /**
     * Get a human-readable description for the charge.
     *
     * Used in receipts, invoices, and Stripe checkout descriptions.
     * Example: "Practice Space - 2 hours on Jan 15, 2026"
     */
    public function getChargeableDescription(): string;

    /**
     * Get the user responsible for payment.
     *
     * This is the user whose credits will be used and who
     * will be charged for any remaining balance.
     */
    public function getBillableUser(): User;

}

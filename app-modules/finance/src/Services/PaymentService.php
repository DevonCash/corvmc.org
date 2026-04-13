<?php

namespace CorvMC\Finance\Services;

use App\Models\User;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Facades\PricingService;
use CorvMC\Finance\Data\CompData;
use CorvMC\Finance\Data\PaymentData;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Facades\ReservationService;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Service class for managing payment operations.
 * 
 * This service consolidates payment-related business logic
 * and provides a clear public API for other modules.
 */
class PaymentService
{
    /**
     * Record a payment for a chargeable entity.
     */
    public function recordPayment(PaymentData $data): ?Charge
    {
        /** @var Chargeable&Model $chargeable */
        $chargeable = $data->chargeable;

        // Special handling for reservations - confirm if needed
        if ($chargeable instanceof RehearsalReservation) {
            if (in_array($chargeable->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
                // TODO: This should be handled via event or callback, not direct dependency
                $chargeable = ReservationService::confirm($chargeable);
                $chargeable->refresh();
            }
        }

        // Get or create charge record
        $charge = $chargeable->charge;
        
        if (!$charge) {
            throw new \Exception('No charge record found for this entity');
        }

        // Mark as paid
        $charge->markAsPaid(
            $data->paymentMethod,
            $data->transactionId,
            $data->paymentIntentId,
            $data->notes
        );

        $this->logPayment($chargeable, $data);

        return $charge;
    }

    /**
     * Mark a chargeable as comped (complimentary/free).
     */
    public function recordComp(CompData $data): Charge
    {
        /** @var Chargeable&Model $chargeable */
        $chargeable = $data->chargeable;

        // Special handling for reservations - confirm if needed
        if ($chargeable instanceof RehearsalReservation) {
            if (in_array($chargeable->status, [ReservationStatus::Scheduled, ReservationStatus::Reserved])) {
                // TODO: This should be handled via event or callback, not direct dependency
                $chargeable = ReservationService::confirm($chargeable);
                $chargeable->refresh();
            }
        }

        // Get or create charge record
        $charge = $chargeable->charge;
        
        if (!$charge) {
            throw new \Exception('No charge record found for this entity');
        }

        // Mark as comped
        $charge->markAsComped($data->reason, $data->notes);

        $this->logComp($chargeable, $data);

        return $charge;
    }

    /**
     * Create a charge for a chargeable entity.
     * This is typically called automatically via event listeners.
     */
    public function createCharge(Chargeable $chargeable, bool $deferCredits = false): Charge
    {
        $user = $chargeable->getBillableUser();

        return DB::transaction(function () use ($chargeable, $user, $deferCredits) {
            // Calculate price with credit application
            $pricing = PricingService::calculatePriceForUser($chargeable, $user);

            // Create Charge record
            $charge = Charge::createForChargeable(
                $chargeable,
                $pricing->amount,
                $pricing->net_amount,
                $pricing->credits_applied ?: null
            );

            // Determine initial status
            if ($pricing->net_amount === 0) {
                // Fully covered by credits - no monetary payment needed
                $charge->markAsCoveredByCredits();
            }

            // Update derived fields on chargeable if supported
            if ($chargeable instanceof Model && $chargeable->isFillable('free_hours_used')) {
                $freeHoursBlocks = $pricing->credits_applied['free_hours'] ?? 0;
                $minutesPerBlock = config('finance.credits.minutes_per_block', 30);
                $chargeable->updateQuietly([
                    'free_hours_used' => ($freeHoursBlocks * $minutesPerBlock) / 60,
                ]);
            }

            // Deduct credits if not deferred
            if (!$deferCredits && !empty($pricing->credits_applied)) {
                $this->deductCredits($user, $pricing->credits_applied, $charge);
            }

            return $charge;
        });
    }

    /**
     * Process successful Stripe payment.
     */
    public function processStripePayment(
        Chargeable $chargeable,
        string $sessionId,
        ?string $paymentIntentId = null
    ): Charge {
        $paymentData = new PaymentData(
            chargeable: $chargeable,
            paymentMethod: 'stripe',
            transactionId: $sessionId,
            paymentIntentId: $paymentIntentId,
            notes: 'Paid via Stripe checkout'
        );

        return $this->recordPayment($paymentData);
    }

    /**
     * Get payment status for a chargeable.
     */
    public function getPaymentStatus(Chargeable $chargeable): ?ChargeStatus
    {
        return $chargeable->charge?->status;
    }

    /**
     * Calculate price for a chargeable with user-specific discounts.
     */
    public function calculatePrice(Chargeable $chargeable, User $user): array
    {
        $pricing = PricingService::calculatePriceForUser($chargeable, $user);

        return [
            'base_amount' => $pricing->amount,
            'net_amount' => $pricing->net_amount,
            'credits_applied' => $pricing->credits_applied,
            'discount_amount' => $pricing->amount - $pricing->net_amount,
        ];
    }

    /**
     * Refund a charge.
     */
    public function refundCharge(Charge $charge, float $amount, string $reason): void
    {
        // TODO: Implement refund logic
        // This would integrate with Stripe for stripe payments
        // and handle credit restoration if applicable
        
        $charge->update([
            'status' => ChargeStatus::Refunded,
            'refunded_amount' => $amount,
            'refund_reason' => $reason,
            'refunded_at' => now(),
        ]);

        activity('payment')
            ->performedOn($charge)
            ->causedBy(auth()->user())
            ->withProperties([
                'amount' => $amount,
                'reason' => $reason,
            ])
            ->log('Charge refunded');
    }

    /**
     * Deduct credits from user.
     */
    protected function deductCredits(User $user, array $creditsApplied, Charge $charge): void
    {
        foreach ($creditsApplied as $creditTypeKey => $blocks) {
            if ($blocks > 0) {
                $creditType = CreditType::from($creditTypeKey);
                $user->deductCredit(
                    $blocks,
                    $creditType,
                    'charge_usage',
                    $charge->id
                );
            }
        }
    }

    /**
     * Log payment activity.
     */
    protected function logPayment(Chargeable $chargeable, PaymentData $data): void
    {
        if ($chargeable instanceof Model) {
            activity('payment')
                ->performedOn($chargeable)
                ->causedBy(auth()->user())
                ->event('payment_recorded')
                ->withProperties([
                    'payment_method' => $data->paymentMethod,
                    'transaction_id' => $data->transactionId,
                    'notes' => $data->notes,
                ])
                ->log("Payment recorded via {$data->paymentMethod}");
        }
    }

    /**
     * Log comp activity.
     */
    protected function logComp(Chargeable $chargeable, CompData $data): void
    {
        if ($chargeable instanceof Model) {
            activity('payment')
                ->performedOn($chargeable)
                ->causedBy(auth()->user())
                ->event('charge_comped')
                ->withProperties([
                    'reason' => $data->reason,
                    'authorized_by' => $data->authorizedBy,
                    'notes' => $data->notes,
                ])
                ->log("Charge comped: {$data->reason}");
        }
    }
}
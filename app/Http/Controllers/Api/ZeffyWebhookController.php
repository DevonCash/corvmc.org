<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UserSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Handles Zapier webhooks from Zeffy payment processing.
 * 
 * Since Zeffy doesn't have a direct API, this controller processes
 * donation notifications sent via Zapier integration.
 */
class ZeffyWebhookController extends Controller
{
    public function __construct(
        private UserSubscriptionService $subscriptionService
    ) {
        // No middleware - webhooks need to be publicly accessible
    }

    /**
     * Handle incoming Zapier webhook from Zeffy for donations and purchases.
     * Note: Zeffy doesn't have a direct API - this handles Zapier webhooks only.
     */
    public function handleWebhook(Request $request): Response
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('Zeffy webhook received', [
                'headers' => $request->headers->all(),
                'payload' => $request->all(),
            ]);

            // Validate the webhook payload
            $validator = $this->validateWebhookPayload($request);
            if ($validator->fails()) {
                Log::warning('Zeffy webhook validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'payload' => $request->all(),
                ]);
                return response('Invalid payload', 400);
            }

            // Process the payment
            $transaction = $this->processPayment($request->all());

            if ($transaction) {
                // Update user subscription status if applicable
                $this->updateUserSubscriptionStatus($transaction);

                Log::info('Zeffy webhook processed successfully', [
                    'transaction_id' => $transaction->transaction_id,
                    'email' => $transaction->email,
                    'amount' => $transaction->amount,
                ]);

                return response('Webhook processed successfully', 200);
            }

            return response('Failed to process payment', 500);

        } catch (\Exception $e) {
            Log::error('Zeffy webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response('Internal server error', 500);
        }
    }

    /**
     * Validate the incoming webhook payload.
     * Since this comes from Zapier, the payload structure may vary.
     */
    private function validateWebhookPayload(Request $request): \Illuminate\Validation\Validator
    {
        // Zapier webhook validation rules - accept both standard and alternative field names
        return Validator::make($request->all(), [
            'donation_id' => 'required_without:id|string',
            'id' => 'required_without:donation_id|string',
            'donor_email' => 'required_without:email|email',
            'email' => 'required_without:donor_email|email',
            'amount' => 'required|numeric|min:0',
            'currency' => 'sometimes|string',
            'status' => 'sometimes|string',
            'type' => 'sometimes|string',
            'is_recurring' => 'sometimes|boolean',
        ]);
    }

    /**
     * Process the payment and create a transaction record.
     */
    private function processPayment(array $payload): ?Transaction
    {
        // Map Zapier payload to our expected format
        $transactionId = $payload['donation_id'] ?? $payload['id'] ?? null;
        $email = $payload['donor_email'] ?? $payload['email'] ?? null;
        
        if (!$transactionId || !$email) {
            Log::warning('Missing required fields in Zapier webhook', $payload);
            return null;
        }

        // Check if this transaction already exists to prevent duplicates
        $existingTransaction = Transaction::where('transaction_id', $transactionId)->first();
        if ($existingTransaction) {
            Log::info('Duplicate Zapier/Zeffy webhook ignored', [
                'transaction_id' => $transactionId,
            ]);
            return $existingTransaction;
        }

        // For Zapier webhooks, we'll assume successful payments unless specified otherwise
        if (!$this->isSuccessfulPayment($payload)) {
            Log::info('Non-successful payment webhook ignored', [
                'transaction_id' => $transactionId,
                'status' => $payload['status'] ?? 'unknown',
            ]);
            return null;
        }

        // Determine transaction type based on payload
        $type = 'donation'; // Default
        if ($payload['is_recurring'] ?? false) {
            $type = 'recurring';
        } elseif (isset($payload['type'])) {
            $type = $payload['type'];
        }

        // Create transaction record
        return Transaction::create([
            'transaction_id' => $transactionId,
            'email' => strtolower(trim($email)),
            'amount' => $payload['amount'],
            'currency' => strtoupper($payload['currency'] ?? 'USD'),
            'type' => $type,
            'response' => $payload,
        ]);
    }

    /**
     * Check if the payment is successful.
     */
    private function isSuccessfulPayment(array $payload): bool
    {
        // For Zapier webhooks from Zeffy, assume successful unless explicitly marked otherwise
        // Since Zapier typically only sends webhooks for successful transactions
        $status = strtolower($payload['status'] ?? 'success');
        
        $successStatuses = ['completed', 'success', 'paid', 'successful', 'confirmed'];
        $failureStatuses = ['failed', 'cancelled', 'declined', 'error'];
        
        // If explicitly marked as failed, don't process
        if (in_array($status, $failureStatuses)) {
            return false;
        }
        
        // Otherwise assume successful (Zapier webhook pattern)
        return true;
    }

    /**
     * Update user subscription status based on the transaction.
     */
    private function updateUserSubscriptionStatus(Transaction $transaction): void
    {
        // Find user by email
        $user = User::where('email', $transaction->email)->first();
        
        if (!$user) {
            Log::info('No user found for transaction email', [
                'email' => $transaction->email,
                'transaction_id' => $transaction->transaction_id,
            ]);
            return;
        }

        // Associate the transaction with the user
        $transaction->update(['user_id' => $user->id]);

        // Check if this qualifies for sustaining membership ($10+ monthly)
        if ($this->qualifiesForSustainingMembership($transaction)) {
            $this->subscriptionService->upgradeToSustainingMember($user, $transaction);
            
            Log::info('User upgraded to sustaining member', [
                'user_id' => $user->id,
                'email' => $user->email,
                'transaction_id' => $transaction->transaction_id,
                'amount' => $transaction->amount,
            ]);
        }

        // Update user's total contribution tracking
        $this->subscriptionService->updateContributionTracking($user, $transaction);
    }

    /**
     * Check if transaction qualifies for sustaining membership.
     */
    private function qualifiesForSustainingMembership(Transaction $transaction): bool
    {
        // $10+ monthly donation or $120+ annual donation
        $monthlyThreshold = 10.00;
        $annualThreshold = 120.00;

        $amount = (float) $transaction->amount;
        $type = strtolower($transaction->type);

        // Check for monthly recurring donations
        if (str_contains($type, 'monthly') || str_contains($type, 'recurring')) {
            return $amount >= $monthlyThreshold;
        }

        // Check for annual donations
        if (str_contains($type, 'annual') || str_contains($type, 'yearly')) {
            return $amount >= $annualThreshold;
        }

        // For one-time donations, check if it's equivalent to a year of sustaining membership
        return $amount >= $annualThreshold;
    }

    /**
     * Handle webhook verification (if Zeffy provides signature verification).
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Zeffy-Signature');
        $secret = config('services.zeffy.webhook_secret');

        if (!$signature || !$secret) {
            return true; // Skip verification if not configured
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $expectedSignature);
    }
}
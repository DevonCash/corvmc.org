<?php

namespace CorvMC\Kiosk\Http\Controllers;

use CorvMC\Events\Models\Event;
use CorvMC\Kiosk\Models\KioskDevice;
use CorvMC\Kiosk\Models\KioskPaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentRequestController extends Controller
{
    /**
     * Create a new payment request (desktop -> tap-to-pay device).
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'amount' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1|max:10',
            'customer_email' => 'nullable|email|max:255',
            'is_sustaining_member' => 'boolean',
            'target_device_id' => 'nullable|exists:kiosk_devices,id',
        ]);

        /** @var KioskDevice $sourceDevice */
        $sourceDevice = $request->attributes->get('kiosk_device');

        // Determine target device
        $targetDeviceId = $request->target_device_id ?? $sourceDevice->payment_device_id;

        if (! $targetDeviceId) {
            return response()->json([
                'message' => 'No tap-to-pay device configured for this kiosk.',
            ], 400);
        }

        $targetDevice = KioskDevice::active()->withTapToPay()->find($targetDeviceId);

        if (! $targetDevice) {
            return response()->json([
                'message' => 'Target tap-to-pay device is not available.',
            ], 400);
        }

        $paymentRequest = KioskPaymentRequest::create([
            'source_device_id' => $sourceDevice->id,
            'target_device_id' => $targetDevice->id,
            'event_id' => $request->event_id,
            'amount' => $request->amount,
            'quantity' => $request->quantity,
            'customer_email' => $request->customer_email,
            'is_sustaining_member' => $request->boolean('is_sustaining_member'),
            'status' => KioskPaymentRequest::STATUS_PENDING,
            'expires_at' => now()->addMinutes(2),
        ]);

        return response()->json([
            'payment_request' => $this->formatPaymentRequest($paymentRequest),
        ], 201);
    }

    /**
     * Get a specific payment request.
     */
    public function show(Request $request, KioskPaymentRequest $paymentRequest): JsonResponse
    {
        return response()->json([
            'payment_request' => $this->formatPaymentRequest($paymentRequest),
        ]);
    }

    /**
     * Cancel a payment request.
     */
    public function cancel(Request $request, KioskPaymentRequest $paymentRequest): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk_device');

        // Only the source device can cancel
        if ($paymentRequest->source_device_id !== $device->id) {
            return response()->json([
                'message' => 'Only the requesting device can cancel this payment.',
            ], 403);
        }

        if (! $paymentRequest->isPending()) {
            return response()->json([
                'message' => 'This payment request cannot be cancelled.',
            ], 400);
        }

        $paymentRequest->markAsCancelled();

        return response()->json([
            'payment_request' => $this->formatPaymentRequest($paymentRequest),
        ]);
    }

    /**
     * Get pending payment requests for this tap-to-pay device.
     */
    public function pending(Request $request): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk_device');

        if (! $device->has_tap_to_pay) {
            return response()->json([
                'message' => 'This device is not configured for tap-to-pay.',
            ], 400);
        }

        $requests = KioskPaymentRequest::forTarget($device)
            ->pending()
            ->notExpired()
            ->with('event')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($pr) => $this->formatPaymentRequest($pr));

        return response()->json(['payment_requests' => $requests]);
    }

    /**
     * Start collecting a payment (tap-to-pay device acknowledges request).
     */
    public function startCollection(Request $request, KioskPaymentRequest $paymentRequest): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk_device');

        if ($paymentRequest->target_device_id !== $device->id) {
            return response()->json([
                'message' => 'This payment request is not for this device.',
            ], 403);
        }

        if ($paymentRequest->isExpired()) {
            $paymentRequest->markAsCancelled();

            return response()->json([
                'message' => 'This payment request has expired.',
            ], 400);
        }

        if (! $paymentRequest->isPending()) {
            return response()->json([
                'message' => 'This payment request is no longer pending.',
            ], 400);
        }

        $paymentRequest->markAsCollecting();

        return response()->json([
            'payment_request' => $this->formatPaymentRequest($paymentRequest),
        ]);
    }

    /**
     * Mark payment as completed (tap-to-pay device collected payment).
     */
    public function complete(Request $request, KioskPaymentRequest $paymentRequest): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk_device');

        if ($paymentRequest->target_device_id !== $device->id) {
            return response()->json([
                'message' => 'This payment request is not for this device.',
            ], 403);
        }

        if (! $paymentRequest->isCollecting() && ! $paymentRequest->isPending()) {
            return response()->json([
                'message' => 'This payment request cannot be completed.',
            ], 400);
        }

        $paymentRequest->markAsCompleted($request->payment_intent_id);

        return response()->json([
            'payment_request' => $this->formatPaymentRequest($paymentRequest),
        ]);
    }

    /**
     * Mark payment as failed (tap-to-pay device encountered an error).
     */
    public function fail(Request $request, KioskPaymentRequest $paymentRequest): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk_device');

        if ($paymentRequest->target_device_id !== $device->id) {
            return response()->json([
                'message' => 'This payment request is not for this device.',
            ], 403);
        }

        $paymentRequest->markAsFailed($request->reason);

        return response()->json([
            'payment_request' => $this->formatPaymentRequest($paymentRequest),
        ]);
    }

    /**
     * Format a payment request for API response.
     */
    private function formatPaymentRequest(KioskPaymentRequest $paymentRequest): array
    {
        $paymentRequest->load('event');

        return [
            'id' => $paymentRequest->id,
            'status' => $paymentRequest->status,
            'amount' => $paymentRequest->amount,
            'quantity' => $paymentRequest->quantity,
            'customer_email' => $paymentRequest->customer_email,
            'is_sustaining_member' => $paymentRequest->is_sustaining_member,
            'payment_intent_id' => $paymentRequest->payment_intent_id,
            'failure_reason' => $paymentRequest->failure_reason,
            'event' => $paymentRequest->event ? [
                'id' => $paymentRequest->event->id,
                'title' => $paymentRequest->event->title,
            ] : null,
            'expires_at' => $paymentRequest->expires_at->toIso8601String(),
            'created_at' => $paymentRequest->created_at->toIso8601String(),
        ];
    }
}

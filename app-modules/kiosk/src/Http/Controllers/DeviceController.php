<?php

namespace CorvMC\Kiosk\Http\Controllers;

use CorvMC\Kiosk\Models\KioskDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeviceController extends Controller
{
    /**
     * Verify device key and return device info with capabilities.
     */
    public function verify(Request $request): JsonResponse
    {
        /** @var KioskDevice $device */
        $device = $request->attributes->get('kiosk_device');

        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'has_tap_to_pay' => $device->has_tap_to_pay,
            'can_do_door_workflow' => $device->canDoDoorWorkflow(),
            'can_push_payments' => $device->canPushPayments(),
            'can_accept_card_payments' => $device->canAcceptCardPayments(),
            'payment_device' => $device->paymentDevice ? [
                'id' => $device->paymentDevice->id,
                'name' => $device->paymentDevice->name,
            ] : null,
        ]);
    }
}

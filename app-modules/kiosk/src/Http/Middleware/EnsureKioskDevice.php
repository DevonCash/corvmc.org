<?php

namespace CorvMC\Kiosk\Http\Middleware;

use Closure;
use CorvMC\Kiosk\Models\KioskDevice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskDevice
{
    /**
     * Handle an incoming request.
     *
     * Validates the X-Device-Key header and ensures the device is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceKey = $request->header('X-Device-Key');

        if (empty($deviceKey)) {
            return response()->json([
                'message' => 'Device key is required.',
                'error' => 'missing_device_key',
            ], 401);
        }

        $device = KioskDevice::where('api_key', $deviceKey)->first();

        if (! $device) {
            return response()->json([
                'message' => 'Invalid device key.',
                'error' => 'invalid_device_key',
            ], 401);
        }

        if (! $device->is_active) {
            return response()->json([
                'message' => 'This device has been deactivated.',
                'error' => 'device_inactive',
            ], 403);
        }

        // Update last seen timestamp (throttled to avoid too many writes)
        if ($device->last_seen_at === null || $device->last_seen_at->diffInMinutes(now()) >= 1) {
            $device->markAsSeen();
        }

        // Attach device to request for use in controllers
        $request->attributes->set('kiosk_device', $device);

        return $next($request);
    }
}

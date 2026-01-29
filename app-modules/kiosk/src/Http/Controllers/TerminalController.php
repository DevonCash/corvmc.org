<?php

namespace CorvMC\Kiosk\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Cashier\Cashier;

class TerminalController extends Controller
{
    /**
     * Create a connection token for Stripe Terminal SDK initialization.
     */
    public function connectionToken(Request $request): JsonResponse
    {
        try {
            $stripe = Cashier::stripe();

            $connectionToken = $stripe->terminal->connectionTokens->create();

            return response()->json([
                'secret' => $connectionToken->secret,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create connection token: '.$e->getMessage(),
            ], 500);
        }
    }
}

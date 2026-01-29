<?php

namespace CorvMC\Kiosk\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    /**
     * Look up a user by email to check membership status.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'found' => false,
                'is_sustaining_member' => false,
            ]);
        }

        return response()->json([
            'found' => true,
            'name' => $user->name,
            'is_sustaining_member' => $user->isSustainingMember(),
        ]);
    }
}

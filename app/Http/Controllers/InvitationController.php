<?php

namespace App\Http\Controllers;

use App\Services\UserInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function __construct(
        protected UserInvitationService $invitationService
    ) {}

    /**
     * Show the invitation acceptance form.
     */
    public function show(string $token)
    {
        // Validate the token and get user info
        $user = $this->invitationService->findUserByToken($token);
        
        if (!$user || $this->invitationService->isTokenExpired($token)) {
            return view('auth.invitation-expired');
        }

        // If user has already completed registration, redirect to login
        if ($user->email_verified_at !== null) {
            return redirect()->route('login')
                ->with('info', 'You have already completed your registration. Please log in.');
        }

        return view('auth.invitation-accept', [
            'token' => $token,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);
    }

    /**
     * Process the invitation acceptance.
     */
    public function store(Request $request, string $token)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->invitationService->acceptInvitation($token, $validated);

        if (!$user) {
            return back()->withErrors([
                'token' => 'This invitation is invalid or has expired.'
            ]);
        }

        // Log the user in automatically
        Auth::login($user);

        // Redirect to member dashboard with welcome message
        return redirect('/member')->with('success', 'Welcome to the Corvallis Music Collective! Your account has been created successfully.');
    }
}
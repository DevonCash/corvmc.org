<?php

namespace App\Http\Controllers;

class InvitationController extends Controller
{
    /**
     * Show the invitation acceptance form.
     */
    public function show(string $token)
    {
        // Find and validate the invitation
        $invitation = \App\Actions\Invitations\FindInvitationByToken::run($token);

        if (! $invitation) {
            return view('auth.invitation-expired', [
                'message' => 'This invitation link is invalid.',
            ]);
        }

        if ($invitation->isExpired()) {
            return view('auth.invitation-expired', [
                'message' => 'This invitation has expired. Please contact us for a new invitation.',
            ]);
        }

        if ($invitation->isUsed()) {
            return redirect()->route('filament.member.auth.login')
                ->with('info', 'This invitation has already been used. Please log in.');
        }

        // Redirect to Filament registration with email prefilled
        return redirect()->route('filament.member.auth.register', [
            'email' => $invitation->email,
            'invitation' => $token,
        ])->with('invitation_message', $invitation->message);
    }
}

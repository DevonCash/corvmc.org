<?php

namespace App\Http\Controllers;

use App\Services\UserSubscriptionService;
use App\Models\User;
use Illuminate\Http\Request;
use Filament\Notifications\Notification;

class SubscriptionCheckoutController extends Controller
{
    public function __construct(
        private UserSubscriptionService $subscriptionService
    ) {}

    /**
     * Handle successful subscription checkout.
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');
        $userId = $request->get('user_id');

        if (!$sessionId || !$userId) {
            Notification::make()
                ->title('Invalid subscription session')
                ->body('Missing required parameters for subscription confirmation.')
                ->danger()
                ->send();

            return redirect()->route('filament.member.resources.users.index');
        }

        $user = User::find($userId);
        if (!$user) {
            Notification::make()
                ->title('User not found')
                ->body('Unable to find the user for this subscription.')
                ->danger()
                ->send();

            return redirect()->route('filament.member.resources.users.index');
        }

        $result = $this->subscriptionService->handleSuccessfulSubscription($user, $sessionId);

        if ($result['success']) {
            Notification::make()
                ->title('Subscription Created Successfully!')
                ->body('You are now a sustaining member. Thank you for your support!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Subscription Processing Error')
                ->body($result['error'] ?? 'Unable to process subscription.')
                ->danger()
                ->send();
        }

        return redirect()->route('filament.member.resources.users.edit', ['record' => $user->id]);
    }

    /**
     * Handle cancelled subscription checkout.
     */
    public function cancel(Request $request)
    {
        $userId = $request->get('user_id');

        Notification::make()
            ->title('Subscription Cancelled')
            ->body('Your subscription checkout was cancelled. You can try again anytime.')
            ->warning()
            ->send();

        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                return redirect()->route('filament.member.resources.users.edit', ['record' => $user->id]);
            }
        }

        return redirect()->route('filament.member.resources.users.index');
    }
}
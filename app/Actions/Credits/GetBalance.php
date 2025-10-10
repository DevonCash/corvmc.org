<?php

namespace App\Actions\Credits;

use App\Models\User;
use App\Models\UserCredit;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBalance
{
    use AsAction;

    /**
     * Get user's current credit balance.
     */
    public function handle(User $user, string $creditType = 'free_hours'): int
    {
        return UserCredit::where('user_id', $user->id)
            ->where('credit_type', $creditType)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->value('balance') ?? 0;
    }
}

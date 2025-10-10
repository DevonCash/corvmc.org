<?php

namespace App\Actions\Credits;

use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use App\Exceptions\InsufficientCreditsException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class DeductCredits
{
    use AsAction;

    /**
     * Deduct credits (e.g., when creating reservation).
     */
    public function handle(
        User $user,
        int $amount,
        string $source,
        ?int $sourceId = null,
        string $creditType = 'free_hours'
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $source, $sourceId, $creditType) {
            $credit = UserCredit::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('credit_type', $creditType)
                ->first();

            if (!$credit || $credit->balance < $amount) {
                $currentBalance = $credit->balance ?? 0;
                throw new InsufficientCreditsException(
                    "User has {$currentBalance} credits but needs {$amount}"
                );
            }

            $credit->balance -= $amount;
            $credit->save();

            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_type' => $creditType,
                'amount' => -$amount,
                'balance_after' => $credit->balance,
                'source' => $source,
                'source_id' => $sourceId,
                'created_at' => now(),
            ]);
        });
    }
}

<?php

namespace App\Actions\Credits;

use App\Models\User;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AddCredits
{
    use AsAction;

    /**
     * Add credits to user's account (transaction-safe).
     */
    public function handle(
        User $user,
        int $amount,
        string $source,
        ?int $sourceId = null,
        ?string $description = null,
        string $creditType = 'free_hours',
        ?Carbon $expiresAt = null
    ): CreditTransaction {
        return DB::transaction(function () use ($user, $amount, $source, $sourceId, $description, $creditType, $expiresAt) {
            // Lock user credit record for update
            $credit = UserCredit::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType],
                    array_merge(
                        ['balance' => 0],
                        $expiresAt ? ['expires_at' => $expiresAt] : [],
                        $this->getDefaultCreditConfig($creditType)
                    )
                );

            // Update balance
            $credit->balance += $amount;
            $credit->save();

            // Record transaction
            return CreditTransaction::create([
                'user_id' => $user->id,
                'credit_type' => $creditType,
                'amount' => $amount,
                'balance_after' => $credit->balance,
                'source' => $source,
                'source_id' => $sourceId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Get default configuration for a credit type.
     */
    protected function getDefaultCreditConfig(string $creditType): array
    {
        return match($creditType) {
            'free_hours' => [
                'balance' => 0,
                'max_balance' => null,
                'rollover_enabled' => false,
            ],
            'equipment_credits' => [
                'balance' => 0,
                'max_balance' => 250,
                'rollover_enabled' => true,
            ],
            default => [
                'balance' => 0,
                'max_balance' => null,
                'rollover_enabled' => false,
            ],
        };
    }
}

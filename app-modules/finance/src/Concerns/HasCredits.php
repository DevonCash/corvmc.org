<?php

namespace CorvMC\Finance\Concerns;

use App\Enums\CreditType;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\Finance\Models\UserCredit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HasCredits Trait
 *
 * Provides credit ledger functionality to models (typically User).
 * Encapsulates the double-entry bookkeeping pattern for credits.
 */
trait HasCredits
{
    /**
     * Get all credit records for this user.
     */
    public function credits(): HasMany
    {
        return $this->hasMany(UserCredit::class, 'user_id');
    }

    /**
     * Get all credit transactions for this user.
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'user_id');
    }

    /**
     * Add credits to this user's account.
     *
     * @param  int  $amount  Amount of credits to add (in blocks)
     * @param  CreditType  $creditType  Type of credit
     * @param  string  $source  What caused this credit addition
     * @param  int|null  $sourceId  ID of the source entity
     * @param  string|null  $description  Human-readable description
     * @param  Carbon|null  $expiresAt  Optional expiration date
     */
    public function addCredit(
        int $amount,
        CreditType $creditType,
        string $source,
        ?int $sourceId = null,
        ?string $description = null,
        ?Carbon $expiresAt = null
    ): CreditTransaction {
        return UserCredit::add($this, $amount, $creditType, $source, $sourceId, $description, $expiresAt);
    }

    /**
     * Deduct credits from this user's account.
     *
     * @param  int  $amount  Amount of credits to deduct (in blocks)
     * @param  CreditType  $creditType  Type of credit
     * @param  string  $source  What caused this credit deduction
     * @param  int|null  $sourceId  ID of the source entity
     *
     * @throws \App\Exceptions\InsufficientCreditsException
     */
    public function deductCredit(
        int $amount,
        CreditType $creditType,
        string $source,
        ?int $sourceId = null
    ): CreditTransaction {
        return UserCredit::deduct($this, $amount, $creditType, $source, $sourceId);
    }

    /**
     * Get credit balance for this user.
     *
     * @param  CreditType  $creditType  Type of credit
     * @return int Balance in blocks
     */
    public function getCreditBalance(CreditType $creditType = CreditType::FreeHours): int
    {
        return UserCredit::getBalance($this, $creditType);
    }
}

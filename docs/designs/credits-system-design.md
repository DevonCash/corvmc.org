# Credits/Coupons System Design

**Status:** Design Proposal
**Created:** October 1, 2025
**Purpose:** Replace fragile month-based free hours calculation with robust credits system

## Problem Statement

Current free hours implementation has several issues:
- **Cache dependency**: Stale cache can cause incorrect calculations
- **Month boundary bugs**: Tests fail depending on run date
- **No audit trail**: Hard to debug why users got specific hours
- **Race conditions**: Concurrent bookings could double-spend hours
- **No flexibility**: Can't do promotions, rollovers, or adjustments
- **Limited features**: Can't grant bonus hours, expire credits, or track sources

## Proposed Solution: Credits System

### Core Concept
Replace "free hours calculated from subscriptions" with "credits that can be spent on reservations."

### Credit Types

**1. Practice Space Credits (`free_hours`)**
- **Source:** Stripe subscription (2 blocks per $5/month)
- **Unit:** 30-minute blocks (minimum rental unit)
- **Usage:** Practice space reservations
- **Refresh:** Monthly (resets to max allocation)
- **Rollover:** No - resets to subscription-based amount each month
- **Example:** $25/month subscription = 10 blocks/month (5 hours)

**2. Equipment Rental Credits (`equipment_credits`)**
- **Source:** Stripe subscription (1 credit per $1/month)
- **Unit:** Individual credits
- **Usage:** Equipment rentals
- **Refresh:** Monthly (adds to existing balance)
- **Rollover:** Yes - accumulates up to 250 credit maximum
- **Cap:** 250 credits total
- **Example:** $50/month subscription = 50 credits/month (accumulates until 250 cap reached)

### Database Schema

```sql
-- User credit balances
CREATE TABLE user_credits (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    credit_type VARCHAR(50) DEFAULT 'free_hours', -- free_hours, equipment_credits, bonus_hours, promo_hours
    balance INTEGER NOT NULL DEFAULT 0, -- Stored in smallest unit: blocks for free_hours, credits for equipment
    max_balance INTEGER NULL, -- Cap for equipment_credits (250), NULL for unlimited
    rollover_enabled BOOLEAN DEFAULT false, -- true for equipment_credits, false for free_hours
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE(user_id, credit_type)
);

-- Credit transactions (audit trail)
CREATE TABLE credit_transactions (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    credit_type VARCHAR(50) NOT NULL,
    amount INTEGER NOT NULL, -- Positive = credit, Negative = debit; stored in smallest unit
    balance_after INTEGER NOT NULL, -- Stored in smallest unit
    source VARCHAR(100) NOT NULL, -- 'monthly_allocation', 'admin_grant', 'reservation_usage', 'promo_code'
    source_id BIGINT NULL, -- ID of reservation, subscription, promo, etc.
    description TEXT NULL,
    metadata JSON NULL, -- Additional context
    created_at TIMESTAMP,

    INDEX(user_id, created_at),
    INDEX(source, source_id)
);

-- Credit allocations (scheduled/recurring)
CREATE TABLE credit_allocations (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    credit_type VARCHAR(50) NOT NULL,
    amount INTEGER NOT NULL, -- Stored in smallest unit
    frequency VARCHAR(20) NOT NULL, -- 'monthly', 'weekly', 'one_time'
    source VARCHAR(100) NOT NULL, -- 'stripe_subscription', 'manual', 'promotion'
    source_id VARCHAR(255) NULL, -- Stripe subscription ID, etc.
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NULL,
    last_allocated_at TIMESTAMP NULL,
    next_allocation_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(user_id, is_active),
    INDEX(next_allocation_at)
);

-- Promotional codes
CREATE TABLE promo_codes (
    id BIGINT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    credit_type VARCHAR(50) NOT NULL,
    credit_amount INTEGER NOT NULL, -- Stored in smallest unit
    max_uses INT NULL, -- NULL = unlimited
    uses_count INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX(code, is_active)
);

-- Promo code redemptions
CREATE TABLE promo_code_redemptions (
    id BIGINT PRIMARY KEY,
    promo_code_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    credit_transaction_id BIGINT NOT NULL,
    redeemed_at TIMESTAMP,

    UNIQUE(promo_code_id, user_id)
);
```

### Service Layer

```php
class CreditService
{
    const PRACTICE_SPACE_BLOCKS_PER_DOLLAR = 2; // 2 blocks (1 hour) per $5
    const PRACTICE_SPACE_DOLLAR_AMOUNT = 5;
    const EQUIPMENT_CREDITS_PER_DOLLAR = 1;
    const MINUTES_PER_BLOCK = 30;

    /**
     * Get user's current credit balance.
     */
    public function getBalance(User $user, string $creditType = 'free_hours'): int
    {
        return UserCredit::where('user_id', $user->id)
            ->where('credit_type', $creditType)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->value('balance') ?? 0;
    }

    /**
     * Convert blocks to hours for display.
     */
    public function blocksToHours(int $blocks): float
    {
        return ($blocks * self::MINUTES_PER_BLOCK) / 60;
    }

    /**
     * Convert hours to blocks for storage.
     */
    public function hoursToBlocks(float $hours): int
    {
        return (int) ceil(($hours * 60) / self::MINUTES_PER_BLOCK);
    }

    /**
     * Add credits to user's account (transaction-safe).
     */
    public function addCredits(
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
                    ['balance' => 0, 'expires_at' => $expiresAt]
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
            ]);
        });
    }

    /**
     * Deduct credits (e.g., when creating reservation).
     */
    public function deductCredits(
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
                throw new InsufficientCreditsException(
                    "User has {$credit->balance} credits but needs {$amount}"
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
            ]);
        });
    }

    /**
     * Allocate monthly credits based on subscription.
     * Handles both practice space (reset) and equipment (rollover) credits.
     */
    public function allocateMonthlyCredits(
        User $user,
        int $amount,
        string $creditType = 'free_hours'
    ): void {
        // Check if allocation already exists for this month
        $allocationKey = "credit_allocation.{$user->id}.{$creditType}." . now()->format('Y-m');

        if (Cache::get($allocationKey)) {
            return; // Already allocated this month
        }

        DB::transaction(function () use ($user, $amount, $creditType) {
            $credit = UserCredit::lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id, 'credit_type' => $creditType],
                    $this->getDefaultCreditConfig($creditType)
                );

            // Handle different allocation strategies
            if ($creditType === 'free_hours') {
                // Practice Space: RESET to subscription amount (no rollover)
                $oldBalance = $credit->balance;
                $credit->balance = $amount;
                $credit->save();

                CreditTransaction::create([
                    'user_id' => $user->id,
                    'credit_type' => $creditType,
                    'amount' => $amount,
                    'balance_after' => $credit->balance,
                    'source' => 'monthly_reset',
                    'description' => "Monthly practice space credits reset for " . now()->format('F Y') . " (previous: {$oldBalance} blocks)",
                ]);
            } elseif ($creditType === 'equipment_credits') {
                // Equipment: ADD to existing balance with cap (rollover enabled)
                $oldBalance = $credit->balance;
                $maxBalance = $credit->max_balance ?? 250; // Default cap

                // Calculate how much we can add without exceeding cap
                $availableSpace = max(0, $maxBalance - $oldBalance);
                $actualAmount = min($amount, $availableSpace);

                if ($actualAmount > 0) {
                    $credit->balance += $actualAmount;
                    $credit->save();

                    CreditTransaction::create([
                        'user_id' => $user->id,
                        'credit_type' => $creditType,
                        'amount' => $actualAmount,
                        'balance_after' => $credit->balance,
                        'source' => 'monthly_allocation',
                        'description' => "Monthly equipment credits allocation for " . now()->format('F Y') .
                                       ($actualAmount < $amount ? " (capped at {$maxBalance})" : ""),
                        'metadata' => json_encode([
                            'requested_amount' => $amount,
                            'actual_amount' => $actualAmount,
                            'cap_reached' => $credit->balance >= $maxBalance,
                        ]),
                    ]);
                }
            }
        });

        // Mark as allocated for this month
        Cache::put($allocationKey, true, now()->endOfMonth());
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

    /**
     * Process all pending allocations.
     * Run via scheduled command: php artisan credits:allocate
     */
    public function processPendingAllocations(): void
    {
        $allocations = CreditAllocation::where('is_active', true)
            ->where('next_allocation_at', '<=', now())
            ->get();

        foreach ($allocations as $allocation) {
            $this->processAllocation($allocation);
        }
    }

    protected function processAllocation(CreditAllocation $allocation): void
    {
        DB::transaction(function () use ($allocation) {
            $this->addCredits(
                $allocation->user,
                $allocation->amount,
                $allocation->source,
                $allocation->id,
                "Automated allocation: {$allocation->frequency}"
            );

            // Update next allocation date
            $allocation->last_allocated_at = now();
            $allocation->next_allocation_at = $this->calculateNextAllocation(
                $allocation->frequency,
                now()
            );
            $allocation->save();
        });
    }

    /**
     * Redeem promo code.
     */
    public function redeemPromoCode(User $user, string $code): CreditTransaction
    {
        $promo = PromoCode::where('code', $code)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        return DB::transaction(function () use ($user, $promo) {
            // Check if already redeemed
            if ($promo->redemptions()->where('user_id', $user->id)->exists()) {
                throw new PromoCodeAlreadyRedeemedException();
            }

            // Check max uses
            if ($promo->max_uses && $promo->uses_count >= $promo->max_uses) {
                throw new PromoCodeMaxUsesException();
            }

            // Add credits
            $transaction = $this->addCredits(
                $user,
                $promo->credit_amount,
                'promo_code',
                $promo->id,
                "Promo code: {$promo->code}",
                $promo->credit_type
            );

            // Record redemption
            PromoCodeRedemption::create([
                'promo_code_id' => $promo->id,
                'user_id' => $user->id,
                'credit_transaction_id' => $transaction->id,
                'redeemed_at' => now(),
            ]);

            // Increment uses
            $promo->increment('uses_count');

            return $transaction;
        });
    }
}
```

### Integration with Existing Code

#### ReservationService Changes

```php
public function calculateCost(User $user, Carbon $startTime, Carbon $endTime): array
{
    $hours = $this->calculateHours($startTime, $endTime);
    $creditService = app(CreditService::class);

    // Get available credits in blocks (replaces getRemainingFreeHours)
    $availableBlocks = $creditService->getBalance($user, 'free_hours');
    $availableHours = $creditService->blocksToHours($availableBlocks);

    $freeHours = $user->isSustainingMember() ? min($hours, $availableHours) : 0;
    $paidHours = max(0, $hours - $freeHours);

    return [
        'hours' => $hours,
        'free_hours' => $freeHours,
        'free_blocks' => $creditService->hoursToBlocks($freeHours),
        'paid_hours' => $paidHours,
        'cost' => Money::of(self::HOURLY_RATE, 'USD')->multipliedBy($paidHours),
    ];
}

public function createReservation(User $user, Carbon $startTime, Carbon $endTime, array $options = []): Reservation
{
    // ... validation

    return DB::transaction(function () use ($user, $startTime, $endTime, $costCalculation, $options) {
        // Create reservation
        $reservation = Reservation::create([...]);

        // Deduct credits if any free hours used
        if ($costCalculation['free_blocks'] > 0) {
            app(CreditService::class)->deductCredits(
                $user,
                $costCalculation['free_blocks'],
                'reservation_usage',
                $reservation->id,
                'free_hours'
            );
        }

        return $reservation;
    });
}
```

#### Stripe Webhook Handler

```php
// When subscription is created/updated
public function handleSubscriptionUpdated(array $payload): void
{
    $subscription = $payload['data']['object'];
    $user = User::where('stripe_id', $subscription['customer'])->first();

    // Calculate subscription amount in dollars
    $amountInDollars = $subscription['items']['data'][0]['price']['unit_amount'] / 100;

    // Calculate practice space credits (2 blocks per $5)
    $blocksGranted = floor($amountInDollars / CreditService::PRACTICE_SPACE_DOLLAR_AMOUNT)
                   * CreditService::PRACTICE_SPACE_BLOCKS_PER_DOLLAR;

    // Calculate equipment credits (1 credit per $1)
    $equipmentCredits = (int) floor($amountInDollars * CreditService::EQUIPMENT_CREDITS_PER_DOLLAR);

    $creditService = app(CreditService::class);

    // 1. Create or update PRACTICE SPACE allocation
    CreditAllocation::updateOrCreate(
        [
            'user_id' => $user->id,
            'source' => 'stripe_subscription',
            'source_id' => $subscription['id'],
            'credit_type' => 'free_hours',
        ],
        [
            'amount' => $blocksGranted,
            'frequency' => 'monthly',
            'starts_at' => now(),
            'next_allocation_at' => now()->startOfMonth()->addMonth(),
            'is_active' => true,
        ]
    );

    // 2. Create or update EQUIPMENT allocation
    CreditAllocation::updateOrCreate(
        [
            'user_id' => $user->id,
            'source' => 'stripe_subscription',
            'source_id' => $subscription['id'],
            'credit_type' => 'equipment_credits',
        ],
        [
            'amount' => $equipmentCredits,
            'frequency' => 'monthly',
            'starts_at' => now(),
            'next_allocation_at' => now()->startOfMonth()->addMonth(),
            'is_active' => true,
        ]
    );

    // Immediately allocate for current month
    $creditService->allocateMonthlyCredits($user, $blocksGranted, 'free_hours');
    $creditService->allocateMonthlyCredits($user, $equipmentCredits, 'equipment_credits');
}
```

### Scheduled Commands

```php
// app/Console/Commands/AllocateCreditsCommand.php
class AllocateCreditsCommand extends Command
{
    protected $signature = 'credits:allocate';
    protected $description = 'Process pending credit allocations';

    public function handle(CreditService $creditService): void
    {
        $creditService->processPendingAllocations();
        $this->info('Credit allocations processed.');
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Run daily at midnight to allocate monthly credits
    $schedule->command('credits:allocate')->daily();
}
```

## Migration Strategy

**Pre-deployment**: No migration needed since the application isn't in production yet.

### Implementation Plan

1. **Build credits system** (Phase 1)
   - Create migrations for new tables
   - Implement `CreditService`
   - Update `ReservationService` to use credits
   - Update Stripe webhook handler

2. **Remove old system** (Phase 1)
   - Delete `getUsedFreeHoursThisMonth()` from `MemberBenefitsService`
   - Delete `getRemainingFreeHours()` from `MemberBenefitsService`
   - Remove free hours cache invalidation from observers
   - Clean up any cache keys related to free hours

3. **Update tests** (Phase 1)
   - Rewrite reservation tests to use credits
   - Add credit transaction tests
   - Test Stripe webhook credit allocation

4. **Deploy** (Phase 1)
   - Single deployment with credits system
   - No feature flags needed
   - No backward compatibility concerns

### If needed for existing test data:
```php
php artisan credits:seed-from-subscriptions
```
- Scan existing Stripe subscriptions
- Create allocations and initial balances
- One-time operation for development/staging environments

## Benefits

### Immediate
- ✅ **Transaction-safe**: Database locks prevent race conditions
- ✅ **Audit trail**: Every credit change is logged
- ✅ **No cache issues**: Fresh data from DB
- ✅ **Testable**: Deterministic behavior

### Long-term
- 🎁 **Promotions**: Easy to run "Get 2 free hours" campaigns
- 🎯 **Referrals**: Give credits for referring friends
- 📅 **Rollover**: Optionally carry unused hours to next month
- 🎟️ **Coupons**: Redeem codes for free hours
- 👥 **Gifts**: Users can gift credits to others
- 📊 **Analytics**: Track credit usage patterns
- 🔧 **Admin tools**: Adjust credits for customer support
- ⏰ **Expiration**: Set expiry dates on promotional credits

## Technical Debt Addressed

- ❌ Remove month-based cache calculations
- ❌ Remove `getUsedFreeHoursThisMonth()` complexity
- ❌ Remove cache invalidation in observers
- ✅ Add proper transaction locking
- ✅ Add comprehensive audit logging
- ✅ Add idempotent allocation logic

## Design Decisions

1. **Integer Storage**: All credits stored as integers
   - ✅ Practice Space: Stored as 30-minute blocks (integers)
   - ✅ Equipment: Stored as individual credits (integers)
   - Rationale: Matches money handling pattern (cents), eliminates floating-point precision issues
   - Unit conversion handled in service layer for display purposes

2. **Rollover Policy**: Different per credit type
   - ✅ **Practice Space Credits (`free_hours`)**: No rollover - reset to subscription amount monthly
   - ✅ **Equipment Credits (`equipment_credits`)**: Full rollover up to 250 credit cap
   - Rationale: Practice space benefits reset to encourage active membership; equipment credits accumulate for flexibility

3. **Practice Space Credit Ratio**: 5:2 dollars to blocks
   - ✅ **Calculation**: 2 blocks (1 hour) per $5 subscription
   - ✅ **Minimum Unit**: 30-minute block
   - Example: $25/month = 10 blocks = 5 hours

4. **Negative Balances**: No overdraft allowed
   - ✅ Throw `InsufficientCreditsException` when balance insufficient
   - Prevents accidental overuse and maintains clear accounting

5. **Multiple Credit Types**: Fully supported
   - ✅ `free_hours` - Practice space (2 blocks per $5, no rollover)
   - ✅ `equipment_credits` - Equipment rentals (1 credit per $1, rollover to 250 cap)
   - ✅ `bonus_hours`, `promo_hours` - Promotional credits (configurable)

6. **Credit Caps**: Type-specific limits
   - ✅ Practice Space: No cap (always resets to subscription amount)
   - ✅ Equipment: 250 credit maximum
   - Stored in `user_credits.max_balance` column

7. **Transfer Between Users**: Phase 2 feature
   - Add after core system stabilizes
   - Will require additional validation and audit controls

## Open Questions

1. **Equipment Credit Cap Notification**: Should we notify users when they hit the 250 cap?
   - Proposal: Yes, send notification when 90% full and when capped

2. **Expired Credit Cleanup**: How long to keep transaction history?
   - Proposal: Keep all transactions indefinitely for audit, but archive after 2 years

3. **Manual Adjustments**: Admin interface for credit corrections?
   - Proposal: Yes, required for customer support (Phase 1)

## Estimated Effort

- **Database migrations**: 2 hours
  - Create 4 new tables (user_credits, credit_transactions, credit_allocations, promo_codes)

- **CreditService implementation**: 4-5 hours
  - Core CRUD operations
  - Allocation logic with rollover handling
  - Promo code redemption

- **Integration**: 3-4 hours
  - Update ReservationService
  - Update Stripe webhook handler
  - Remove old free hours methods

- **Testing**: 3-4 hours
  - Rewrite reservation workflow tests
  - Add credit transaction tests
  - Test both credit types (practice space + equipment)

- **Models & Migrations**: 1-2 hours
  - UserCredit, CreditTransaction, CreditAllocation, PromoCode models
  - Relationships and casts

**Total: 13-17 hours**

## Success Metrics

- Zero cache-related bugs in free hours
- 100% transaction success rate (no race conditions)
- Sub-100ms credit balance lookups
- Full audit trail for all credit changes
- Support for promotional campaigns

## References

- Laravel Database Transactions: https://laravel.com/docs/database#database-transactions
- Optimistic Locking Patterns
- Double-Entry Bookkeeping for Credits
- Stripe Billing Credits API (for future integration)

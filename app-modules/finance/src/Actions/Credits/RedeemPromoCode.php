<?php

namespace CorvMC\Finance\Actions\Credits;

use App\Enums\CreditType;
use App\Exceptions\PromoCodeAlreadyRedeemedException;
use App\Exceptions\PromoCodeMaxUsesException;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\Finance\Models\PromoCode;
use CorvMC\Finance\Models\PromoCodeRedemption;
use CorvMC\Membership\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RedeemPromoCode
{
    use AsAction;

    /**
     * Redeem promo code.
     */
    public function handle(User $user, string $code): CreditTransaction
    {
        $promo = PromoCode::where('code', $code)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        return DB::transaction(function () use ($user, $promo) {
            // Check if already redeemed
            if ($promo->redemptions()->where('user_id', $user->id)->exists()) {
                throw new PromoCodeAlreadyRedeemedException;
            }

            // Check max uses
            if ($promo->max_uses && $promo->uses_count >= $promo->max_uses) {
                throw new PromoCodeMaxUsesException;
            }

            // Add credits
            $creditType = CreditType::from($promo->credit_type);
            $transaction = $user->addCredit(
                $promo->credit_amount,
                $creditType,
                'promo_code',
                $promo->id,
                "Promo code: {$promo->code}"
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

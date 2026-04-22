<?php

namespace CorvMC\Finance\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(array $productClasses)
 * @method static \CorvMC\Finance\Products\Product productFor(\Illuminate\Database\Eloquent\Model $model)
 * @method static \CorvMC\Finance\Products\Product productByType(string $type)
 * @method static string[] registeredTypes()
 * @method static bool isRegisteredType(string $type)
 * @method static int balance(\App\Models\User $user, string $walletType)
 * @method static void allocate(\App\Models\User $user, string $walletType, int $amount, string $reason, ?\Illuminate\Database\Eloquent\Model $source = null)
 * @method static void adjust(\App\Models\User $user, string $walletType, int $amount, string $reason)
 *
 * @see \CorvMC\Finance\FinanceManager
 */
class Finance extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CorvMC\Finance\FinanceManager::class;
    }
}

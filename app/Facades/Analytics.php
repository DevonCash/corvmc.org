<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getEquipmentStats()
 * @method static array getUserStats()
 * @method static \CorvMC\Finance\Data\SubscriptionStatsData getSubscriptionStats()
 * @method static array getUserReservationStats(\App\Models\User $user)
 * @method static \CorvMC\SpaceManagement\Data\ReservationUsageData getUserReservationUsageForMonth(\App\Models\User $user, \Carbon\Carbon $month)
 * @method static array getDirectoryStats()
 * @method static array getStaffProfileStats()
 * @method static array getTrustStatistics(\App\Models\User $user)
 * @method static array getReportStatistics(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null)
 * @method static array getDashboardStats(string $panel = 'staff')
 * @method static void invalidateCache(string|array|null $keys = null)
 * 
 * @see \App\Services\AnalyticsService
 */
class Analytics extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\AnalyticsService::class;
    }
}
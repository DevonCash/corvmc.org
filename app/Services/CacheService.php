<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Clear all user-related caches for a specific user.
     */
    public static function clearUserCaches(int $userId): void
    {
        Cache::forget("user.{$userId}.is_sustaining");
        Cache::forget("user.{$userId}.free_hours." . now()->format('Y-m'));
        Cache::forget("user_stats.{$userId}");
        Cache::forget("user_activity.{$userId}");
        
        // Clear global caches that might include this user
        Cache::forget('sustaining_members');
        Cache::forget('subscription_stats');
    }

    /**
     * Clear all reservation-related caches for a specific date.
     */
    public static function clearReservationCaches(string $date): void
    {
        Cache::forget("reservations.conflicts.{$date}");
    }

    /**
     * Clear all production-related caches for a specific date.
     */
    public static function clearProductionCaches(string $date): void
    {
        Cache::forget("productions.conflicts.{$date}");
        Cache::forget('upcoming_events');
        
        // Note: We can't easily clear all user-specific upcoming events caches
        // without scanning all keys, so they'll expire naturally
    }

    /**
     * Clear all member directory filter caches.
     */
    public static function clearMemberDirectoryCaches(): void
    {
        Cache::tags(['member_directory', 'tags'])->flush();
    }

    /**
     * Clear all subscription-related caches.
     */
    public static function clearSubscriptionCaches(): void
    {
        Cache::forget('sustaining_members');
        Cache::forget('subscription_stats');
    }

    /**
     * Warm up commonly used caches.
     */
    public static function warmUpCaches(): void
    {
        // Warm up tag caches for member directory
        Cache::tags(['member_directory', 'tags'])->remember('member_directory.skills', 3600, function() {
            return \Spatie\Tags\Tag::where('type', 'skill')->pluck('name', 'name')->toArray();
        });

        Cache::tags(['member_directory', 'tags'])->remember('member_directory.genres', 3600, function() {
            return \Spatie\Tags\Tag::where('type', 'genre')->pluck('name', 'name')->toArray();
        });

        Cache::tags(['member_directory', 'tags'])->remember('member_directory.influences', 3600, function() {
            return \Spatie\Tags\Tag::where('type', 'influence')->pluck('name', 'name')->toArray();
        });

        // Warm up subscription stats
        \UserSubscriptionService::getSubscriptionStats();
        \UserSubscriptionService::getSustainingMembers();
    }

    /**
     * Get cache statistics for monitoring.
     */
    public static function getCacheStats(): array
    {
        // This would require Redis to get detailed stats
        // For now, return basic info
        return [
            'cache_driver' => config('cache.default'),
            'redis_connection' => config('cache.stores.redis.connection'),
            'timestamp' => now(),
        ];
    }

    /**
     * Clear all application caches (use with caution).
     */
    public static function clearAllCaches(): void
    {
        Cache::flush();
    }

    /**
     * Clear caches for a specific date range.
     */
    public static function clearDateRangeCaches(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): void
    {
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');
            Cache::forget("reservations.conflicts.{$dateString}");
            Cache::forget("productions.conflicts.{$dateString}");
            $currentDate->addDay();
        }
    }

    /**
     * Get cache key for user-specific data.
     */
    public static function getUserCacheKey(int $userId, string $type, string $suffix = ''): string
    {
        return "user.{$userId}.{$type}" . ($suffix ? ".{$suffix}" : '');
    }

    /**
     * Get cache key for date-specific data.
     */
    public static function getDateCacheKey(string $type, string $date): string
    {
        return "{$type}.{$date}";
    }
}
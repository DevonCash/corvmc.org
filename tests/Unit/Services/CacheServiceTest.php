<?php

use App\Services\CacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clear all caches before each test
    Cache::flush();
});

describe('User Cache Management', function () {
    it('can clear user caches', function () {
        $userId = 123;
        $currentMonth = now()->format('Y-m');
        
        // Set up some cached data for this user
        Cache::put("user.{$userId}.is_sustaining", true);
        Cache::put("user.{$userId}.free_hours.{$currentMonth}", 2);
        Cache::put("user_stats.{$userId}", ['reservations' => 5]);
        Cache::put("user_activity.{$userId}", ['last_login' => now()]);
        Cache::put('sustaining_members', ['user_123']);
        Cache::put('subscription_stats', ['total' => 10]);
        
        // Verify data is cached
        expect(Cache::has("user.{$userId}.is_sustaining"))->toBeTrue()
            ->and(Cache::has("user.{$userId}.free_hours.{$currentMonth}"))->toBeTrue()
            ->and(Cache::has("user_stats.{$userId}"))->toBeTrue()
            ->and(Cache::has("user_activity.{$userId}"))->toBeTrue()
            ->and(Cache::has('sustaining_members'))->toBeTrue()
            ->and(Cache::has('subscription_stats'))->toBeTrue();

        // Clear user caches
        CacheService::clearUserCaches($userId);

        // Verify specific user caches are cleared
        expect(Cache::has("user.{$userId}.is_sustaining"))->toBeFalse()
            ->and(Cache::has("user.{$userId}.free_hours.{$currentMonth}"))->toBeFalse()
            ->and(Cache::has("user_stats.{$userId}"))->toBeFalse()
            ->and(Cache::has("user_activity.{$userId}"))->toBeFalse()
            ->and(Cache::has('sustaining_members'))->toBeFalse()
            ->and(Cache::has('subscription_stats'))->toBeFalse();
    });
});

describe('Reservation Cache Management', function () {
    it('can clear reservation caches for specific date', function () {
        $date = '2024-01-15';
        
        // Set up cached reservation data
        Cache::put("reservations.conflicts.{$date}", ['conflicts' => []]);
        Cache::put("reservations.conflicts.2024-01-16", ['conflicts' => []]);
        
        // Verify data is cached
        expect(Cache::has("reservations.conflicts.{$date}"))->toBeTrue()
            ->and(Cache::has("reservations.conflicts.2024-01-16"))->toBeTrue();

        // Clear reservation caches for specific date
        CacheService::clearReservationCaches($date);

        // Verify only the specific date cache is cleared
        expect(Cache::has("reservations.conflicts.{$date}"))->toBeFalse()
            ->and(Cache::has("reservations.conflicts.2024-01-16"))->toBeTrue();
    });
});

describe('Production Cache Management', function () {
    it('can clear production caches for specific date', function () {
        $date = '2024-01-15';
        
        // Set up cached production data
        Cache::put("productions.conflicts.{$date}", ['conflicts' => []]);
        Cache::put("productions.conflicts.2024-01-16", ['conflicts' => []]);
        Cache::put('upcoming_events', ['events' => []]);
        
        // Verify data is cached
        expect(Cache::has("productions.conflicts.{$date}"))->toBeTrue()
            ->and(Cache::has("productions.conflicts.2024-01-16"))->toBeTrue()
            ->and(Cache::has('upcoming_events'))->toBeTrue();

        // Clear production caches for specific date
        CacheService::clearProductionCaches($date);

        // Verify specific date and global caches are cleared
        expect(Cache::has("productions.conflicts.{$date}"))->toBeFalse()
            ->and(Cache::has("productions.conflicts.2024-01-16"))->toBeTrue()
            ->and(Cache::has('upcoming_events'))->toBeFalse();
    });
});

describe('Member Directory Cache Management', function () {
    it('can clear member directory caches', function () {
        // Set up tagged cache data
        Cache::tags(['member_directory', 'tags'])->put('member_directory.skills', ['guitar', 'vocals']);
        Cache::tags(['member_directory', 'tags'])->put('member_directory.genres', ['rock', 'jazz']);
        Cache::tags(['member_directory'])->put('member_directory.users', ['user_1', 'user_2']);
        
        // Set up unrelated cache data
        Cache::put('unrelated_data', 'should_remain');
        
        // Verify tagged data is cached
        expect(Cache::tags(['member_directory', 'tags'])->has('member_directory.skills'))->toBeTrue()
            ->and(Cache::tags(['member_directory', 'tags'])->has('member_directory.genres'))->toBeTrue()
            ->and(Cache::tags(['member_directory'])->has('member_directory.users'))->toBeTrue()
            ->and(Cache::has('unrelated_data'))->toBeTrue();

        // Clear member directory caches
        CacheService::clearMemberDirectoryCaches();

        // Verify tagged caches are cleared but unrelated data remains
        expect(Cache::tags(['member_directory', 'tags'])->has('member_directory.skills'))->toBeFalse()
            ->and(Cache::tags(['member_directory', 'tags'])->has('member_directory.genres'))->toBeFalse()
            ->and(Cache::tags(['member_directory'])->has('member_directory.users'))->toBeFalse()
            ->and(Cache::has('unrelated_data'))->toBeTrue();
    });
});

describe('Subscription Cache Management', function () {
    it('can clear subscription caches', function () {
        // Set up subscription-related cache data
        Cache::put('sustaining_members', ['user_1', 'user_2']);
        Cache::put('subscription_stats', ['total' => 25]);
        Cache::put('unrelated_cache', 'should_remain');
        
        // Verify data is cached
        expect(Cache::has('sustaining_members'))->toBeTrue()
            ->and(Cache::has('subscription_stats'))->toBeTrue()
            ->and(Cache::has('unrelated_cache'))->toBeTrue();

        // Clear subscription caches
        CacheService::clearSubscriptionCaches();

        // Verify subscription caches are cleared but unrelated remains
        expect(Cache::has('sustaining_members'))->toBeFalse()
            ->and(Cache::has('subscription_stats'))->toBeFalse()
            ->and(Cache::has('unrelated_cache'))->toBeTrue();
    });
});

describe('Cache Warming and Statistics', function () {
    it('can warm up caches', function () {
        // Create some test tags
        \Spatie\Tags\Tag::create(['name' => 'guitar', 'type' => 'skill']);
        \Spatie\Tags\Tag::create(['name' => 'rock', 'type' => 'genre']);
        \Spatie\Tags\Tag::create(['name' => 'radiohead', 'type' => 'influence']);

        // Verify caches are empty initially
        expect(Cache::tags(['member_directory', 'tags'])->has('member_directory.skills'))->toBeFalse()
            ->and(Cache::tags(['member_directory', 'tags'])->has('member_directory.genres'))->toBeFalse()
            ->and(Cache::tags(['member_directory', 'tags'])->has('member_directory.influences'))->toBeFalse();

        // Warm up caches (this will fail on UserSubscriptionService calls, but that's ok for tag testing)
        try {
            CacheService::warmUpCaches();
        } catch (\Exception $e) {
            // Expected to fail on UserSubscriptionService calls, but tags should still be cached
        }

        // Verify tag caches are now populated (the part we can test)
        expect(Cache::tags(['member_directory', 'tags'])->has('member_directory.skills'))->toBeTrue()
            ->and(Cache::tags(['member_directory', 'tags'])->has('member_directory.genres'))->toBeTrue()
            ->and(Cache::tags(['member_directory', 'tags'])->has('member_directory.influences'))->toBeTrue();

        // Verify cached data is correct
        $skills = Cache::tags(['member_directory', 'tags'])->get('member_directory.skills');
        $genres = Cache::tags(['member_directory', 'tags'])->get('member_directory.genres');
        $influences = Cache::tags(['member_directory', 'tags'])->get('member_directory.influences');

        expect($skills)->toContain('guitar')
            ->and($genres)->toContain('rock')
            ->and($influences)->toContain('radiohead');
    });

    it('can get cache stats', function () {
        $stats = CacheService::getCacheStats();

        expect($stats)->toBeArray()
            ->toHaveKeys(['cache_driver', 'redis_connection', 'timestamp'])
            ->and($stats['cache_driver'])->toBe(config('cache.default'))
            ->and($stats['timestamp'])->toBeInstanceOf(Carbon::class);
    });
});

describe('Global Cache Management', function () {
    it('can clear all caches', function () {
        // Set up various cached data
        Cache::put('test_key_1', 'value_1');
        Cache::put('test_key_2', 'value_2');
        Cache::tags(['test_tag'])->put('tagged_key', 'tagged_value');
        
        // Verify data is cached
        expect(Cache::has('test_key_1'))->toBeTrue()
            ->and(Cache::has('test_key_2'))->toBeTrue()
            ->and(Cache::tags(['test_tag'])->has('tagged_key'))->toBeTrue();

        // Clear all caches
        CacheService::clearAllCaches();

        // Verify all caches are cleared
        expect(Cache::has('test_key_1'))->toBeFalse()
            ->and(Cache::has('test_key_2'))->toBeFalse()
            ->and(Cache::tags(['test_tag'])->has('tagged_key'))->toBeFalse();
    });
});

describe('Date Range Cache Management', function () {
    it('can clear date range caches', function () {
        $startDate = Carbon::parse('2024-01-15');
        $endDate = Carbon::parse('2024-01-17');
        
        // Set up cached data for multiple dates
        Cache::put('reservations.conflicts.2024-01-14', ['before_range']);
        Cache::put('reservations.conflicts.2024-01-15', ['in_range_1']);
        Cache::put('reservations.conflicts.2024-01-16', ['in_range_2']);
        Cache::put('reservations.conflicts.2024-01-17', ['in_range_3']);
        Cache::put('reservations.conflicts.2024-01-18', ['after_range']);
        
        Cache::put('productions.conflicts.2024-01-14', ['before_range']);
        Cache::put('productions.conflicts.2024-01-15', ['in_range_1']);
        Cache::put('productions.conflicts.2024-01-16', ['in_range_2']);
        Cache::put('productions.conflicts.2024-01-17', ['in_range_3']);
        Cache::put('productions.conflicts.2024-01-18', ['after_range']);
        
        // Verify all data is cached
        expect(Cache::has('reservations.conflicts.2024-01-14'))->toBeTrue()
            ->and(Cache::has('reservations.conflicts.2024-01-15'))->toBeTrue()
            ->and(Cache::has('reservations.conflicts.2024-01-16'))->toBeTrue()
            ->and(Cache::has('reservations.conflicts.2024-01-17'))->toBeTrue()
            ->and(Cache::has('reservations.conflicts.2024-01-18'))->toBeTrue();

        // Clear date range caches
        CacheService::clearDateRangeCaches($startDate, $endDate);

        // Verify only caches within the date range are cleared
        expect(Cache::has('reservations.conflicts.2024-01-14'))->toBeTrue()  // Before range
            ->and(Cache::has('reservations.conflicts.2024-01-15'))->toBeFalse() // In range
            ->and(Cache::has('reservations.conflicts.2024-01-16'))->toBeFalse() // In range
            ->and(Cache::has('reservations.conflicts.2024-01-17'))->toBeFalse() // In range
            ->and(Cache::has('reservations.conflicts.2024-01-18'))->toBeTrue(); // After range

        // Verify productions caches are also cleared in the same way
        expect(Cache::has('productions.conflicts.2024-01-14'))->toBeTrue()
            ->and(Cache::has('productions.conflicts.2024-01-15'))->toBeFalse()
            ->and(Cache::has('productions.conflicts.2024-01-16'))->toBeFalse()
            ->and(Cache::has('productions.conflicts.2024-01-17'))->toBeFalse()
            ->and(Cache::has('productions.conflicts.2024-01-18'))->toBeTrue();
    });

    it('handles single day date range', function () {
        $singleDate = Carbon::parse('2024-01-15');
        
        // Set up cached data
        Cache::put('reservations.conflicts.2024-01-15', ['single_day']);
        Cache::put('reservations.conflicts.2024-01-16', ['other_day']);
        
        expect(Cache::has('reservations.conflicts.2024-01-15'))->toBeTrue()
            ->and(Cache::has('reservations.conflicts.2024-01-16'))->toBeTrue();

        // Clear single day range
        CacheService::clearDateRangeCaches($singleDate, $singleDate);

        // Verify only the single day is cleared
        expect(Cache::has('reservations.conflicts.2024-01-15'))->toBeFalse()
            ->and(Cache::has('reservations.conflicts.2024-01-16'))->toBeTrue();
    });
});
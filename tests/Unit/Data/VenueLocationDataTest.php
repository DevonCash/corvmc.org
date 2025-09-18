<?php

use App\Data\VenueLocationData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

uses()->group('community-events', 'venue-location');

test('it can be created with basic venue info', function ()
 {
    $venue = VenueLocationData::create('Test Venue', '123 Main St, Corvallis, OR');
    
    expect($venue->venue_name)->toBe('Test Venue');
    expect($venue->venue_address)->toBe('123 Main St, Corvallis, OR');
    expect($venue->distance_from_corvallis)->toBeNull();
    expect($venue->latitude)->toBeNull();
    expect($venue->longitude)->toBeNull();
});

test('it can create cmc location', function () {
    $cmcVenue = VenueLocationData::cmc();
    
    expect($cmcVenue->venue_name)->toBe('Corvallis Music Collective');
    expect($cmcVenue->venue_address)->toBe('Corvallis, OR, USA');
    expect($cmcVenue->distance_from_corvallis)->toBe(0.0);
    expect($cmcVenue->latitude)->toBe(44.5646);
    expect($cmcVenue->longitude)->toBe(-123.2620);
});

test('it validates addresses correctly', function () {
    // Valid address
    $validVenue = VenueLocationData::create('Valid Venue', '123 Main St, Corvallis, OR');
    $errors = $validVenue->validateAddress();
    expect($errors)->toBeEmpty();

    // Missing venue name
    $noNameVenue = VenueLocationData::create('', '123 Main St, Corvallis, OR');
    $errors = $noNameVenue->validateAddress();
    expect($errors)->toContain('Venue name is required');

    // Missing address
    $noAddressVenue = VenueLocationData::create('Test Venue', '');
    $errors = $noAddressVenue->validateAddress();
    expect($errors)->toContain('Venue address is required');

    // Incomplete address (no number or comma)
    $incompleteVenue = VenueLocationData::create('Test Venue', 'Main Street');
    $errors = $incompleteVenue->validateAddress();
    expect($errors)->toContain('Please provide a complete address with street number and city');
});

test('it determines distance acceptance correctly', function () {
    // Within default limit (60 minutes)
    $closeVenue = new VenueLocationData(
        venue_name: 'Close Venue',
        venue_address: '123 Main St',
        distance_from_corvallis: 30
    );
    expect($closeVenue->isWithinDistance())->toBeTrue();
    expect($closeVenue->isWithinDistance(60))->toBeTrue();

    // Beyond default limit
    $farVenue = new VenueLocationData(
        venue_name: 'Far Venue',
        venue_address: '456 Far St',
        distance_from_corvallis: 90
    );
    expect($farVenue->isWithinDistance())->toBeFalse();
    expect($farVenue->isWithinDistance(60))->toBeFalse();
    expect($farVenue->isWithinDistance(120))->toBeTrue(); // But within custom limit

    // Unknown distance (should be accepted)
    $unknownVenue = new VenueLocationData(
        venue_name: 'Unknown Venue',
        venue_address: '789 Unknown St'
    );
    expect($unknownVenue->isWithinDistance())->toBeTrue();
});

test('it formats driving time correctly', function () {
    // Less than an hour
    $shortDrive = new VenueLocationData(
        venue_name: 'Short Drive',
        venue_address: '123 Main St',
        distance_from_corvallis: 45
    );
    expect($shortDrive->getDrivingTimeDisplay())->toBe('45 min');

    // Exactly one hour
    $oneHour = new VenueLocationData(
        venue_name: 'One Hour',
        venue_address: '456 Main St',
        distance_from_corvallis: 60
    );
    expect($oneHour->getDrivingTimeDisplay())->toBe('1 hr 0 min');

    // More than one hour
    $longDrive = new VenueLocationData(
        venue_name: 'Long Drive',
        venue_address: '789 Main St',
        distance_from_corvallis: 95
    );
    expect($longDrive->getDrivingTimeDisplay())->toBe('1 hr 35 min');

    // Unknown distance
    $unknownDrive = new VenueLocationData(
        venue_name: 'Unknown',
        venue_address: '999 Main St'
    );
    expect($unknownDrive->getDrivingTimeDisplay())->toBeNull();
});

test('it generates correct directions url', function () {
    $venue = VenueLocationData::create('Test Venue', '123 Main St, Portland, OR');
    
    $url = $venue->getDirectionsUrl();
    
    expect($url)->toContain('https://www.google.com/maps/dir/');
    expect($url)->toContain(urlencode('Corvallis, OR, USA'));
    expect($url)->toContain(urlencode('123 Main St, Portland, OR'));
});

test('it displays venue information correctly', function () {
    $venue = new VenueLocationData(
        venue_name: 'Test Venue',
        venue_address: '123 Main St, Portland, OR',
        formatted_address: '123 Main Street, Portland, OR 97201, USA'
    );
    
    $display = $venue->getFullVenueDisplay();
    expect($display)->toBe('Test Venue - 123 Main Street, Portland, OR 97201, USA');

    // Without formatted address, should use original
    $venue2 = VenueLocationData::create('Test Venue 2', '456 Oak St, Eugene, OR');
    $display2 = $venue2->getFullVenueDisplay();
    expect($display2)->toBe('Test Venue 2 - 456 Oak St, Eugene, OR');
});

test('it provides distance warnings', function () {
    // Within acceptable distance - no warning
    $closeVenue = new VenueLocationData(
        venue_name: 'Close Venue',
        venue_address: '123 Main St',
        distance_from_corvallis: 30
    );
    expect($closeVenue->getDistanceWarning(60))->toBeNull();

    // Beyond acceptable distance - warning
    $farVenue = new VenueLocationData(
        venue_name: 'Far Venue',
        venue_address: '456 Far St',
        distance_from_corvallis: 90
    );
    $warning = $farVenue->getDistanceWarning(60);
    expect($warning)->toContain('1 hr 30 min from Corvallis');
    expect($warning)->toContain('exceeds the recommended 60-minute limit');

    // Unknown distance - warning
    $unknownVenue = new VenueLocationData(
        venue_name: 'Unknown Venue',
        venue_address: '789 Unknown St'
    );
    $warning = $unknownVenue->getDistanceWarning();
    expect($warning)->toContain('Distance from Corvallis could not be calculated');
});

test('it calculates distance with mocked api', function () {
    // Mock successful Google Maps API response
    Http::fake([
        'maps.googleapis.com/maps/api/directions/json*' => Http::response([
            'status' => 'OK',
            'routes' => [
                [
                    'legs' => [
                        [
                            'duration' => ['value' => 3600], // 1 hour in seconds
                            'end_address' => '123 Test Street, Portland, OR 97201, USA',
                            'end_location' => [
                                'lat' => 45.5152,
                                'lng' => -122.6784
                            ]
                        ]
                    ]
                ]
            ]
        ])
    ]);

    Cache::flush(); // Clear any cached results

    $venue = VenueLocationData::create('Test Venue', '123 Test Street, Portland, OR');
    $result = $venue->calculateDistance('fake-api-key');

    expect($result->distance_from_corvallis)->toBe(60.0); // 3600 seconds / 60 = 60 minutes
    expect($result->formatted_address)->toBe('123 Test Street, Portland, OR 97201, USA');
    expect($result->latitude)->toBe(45.5152);
    expect($result->longitude)->toBe(-122.6784);
});

test('it handles api errors gracefully', function () {
    // Mock API error response
    Http::fake([
        'maps.googleapis.com/maps/api/directions/json*' => Http::response([
            'status' => 'NOT_FOUND',
            'error_message' => 'Address not found'
        ])
    ]);

    Cache::flush();

    $venue = VenueLocationData::create('Invalid Venue', 'Invalid Address');
    $result = $venue->calculateDistance('fake-api-key');

    // Should return original venue without distance data
    expect($result->venue_name)->toBe('Invalid Venue');
    expect($result->venue_address)->toBe('Invalid Address');
    expect($result->distance_from_corvallis)->toBeNull();
});

test('it handles missing api key', function () {
    $venue = VenueLocationData::create('Test Venue', '123 Main St');
    $result = $venue->calculateDistance(null);

    // Should return original venue without attempting calculation
    expect($result)->toBe($venue);
    expect($result->distance_from_corvallis)->toBeNull();
});

test('it caches distance calculations', function () {
    Cache::flush();

    // Mock API response
    Http::fake([
        'maps.googleapis.com/maps/api/directions/json*' => Http::response([
            'status' => 'OK',
            'routes' => [
                [
                    'legs' => [
                        [
                            'duration' => ['value' => 1800], // 30 minutes
                            'end_address' => 'Cached Address',
                            'end_location' => ['lat' => 44.5, 'lng' => -123.3]
                        ]
                    ]
                ]
            ]
        ])
    ]);

    $venue = VenueLocationData::create('Test Venue', '123 Cache Test St');
    
    // First call should hit the API
    $result1 = $venue->calculateDistance('fake-api-key');
    
    // Second call with same address should use cache
    $venue2 = VenueLocationData::create('Different Name', '123 Cache Test St');
    $result2 = $venue2->calculateDistance('fake-api-key');

    // Should have same distance data (from cache)
    expect($result1->distance_from_corvallis)->toBe(30.0);
    expect($result2->distance_from_corvallis)->toBe(30.0);
    expect($result2->formatted_address)->toBe('Cached Address');

    // Verify only one API call was made
    Http::assertSentCount(1);
});

test('it handles network failures', function () {
    // Mock network failure
    Http::fake(function () {
        throw new \Exception('Network error');
    });

    Cache::flush();

    $venue = VenueLocationData::create('Test Venue', '123 Network Fail St');
    $result = $venue->calculateDistance('fake-api-key');

    // Should return original venue data without throwing exception
    expect($result->venue_name)->toBe('Test Venue');
    expect($result->venue_address)->toBe('123 Network Fail St');
    expect($result->distance_from_corvallis)->toBeNull();
});
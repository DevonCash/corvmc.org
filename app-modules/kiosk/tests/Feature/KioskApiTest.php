<?php

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Events\Actions\CreateEvent;
use CorvMC\Events\Actions\Tickets\CreateDoorSale;
use CorvMC\Events\Models\Venue;
use CorvMC\Kiosk\Models\KioskDevice;
use CorvMC\Kiosk\Models\KioskPaymentRequest;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create CMC venue
    $this->cmcVenue = Venue::create([
        'name' => 'Corvallis Music Collective',
        'address' => '420 SW Washington Ave',
        'city' => 'Corvallis',
        'state' => 'OR',
        'zip' => '97333',
        'is_cmc' => true,
    ]);

    // Create an event with ticketing enabled
    $startDatetime = Carbon::now()->addDays(1)->setHour(19)->setMinute(0)->setSecond(0);
    $this->event = CreateEvent::run([
        'title' => 'Test Concert',
        'start_datetime' => $startDatetime,
        'end_datetime' => $startDatetime->copy()->addHours(3),
        'venue_id' => $this->cmcVenue->id,
        'ticketing_enabled' => true,
        'ticket_quantity' => 100,
    ]);

    // Create kiosk device
    $this->device = KioskDevice::create([
        'name' => 'Test Kiosk',
        'is_active' => true,
        'has_tap_to_pay' => false,
    ]);

    // Create staff user
    $this->staffUser = User::factory()->create();
    $this->staffUser->assignRole('staff');
});

describe('Kiosk: Device Verification', function () {
    it('returns 401 without device key', function () {
        $this->getJson('/api/v1/kiosk/device/verify')
            ->assertStatus(401)
            ->assertJson(['error' => 'missing_device_key']);
    });

    it('returns 401 with invalid device key', function () {
        $this->getJson('/api/v1/kiosk/device/verify', [
            'X-Device-Key' => 'invalid-key',
        ])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_device_key']);
    });

    it('returns 403 for inactive device', function () {
        $this->device->update(['is_active' => false]);

        $this->getJson('/api/v1/kiosk/device/verify', [
            'X-Device-Key' => $this->device->api_key,
        ])
            ->assertStatus(403)
            ->assertJson(['error' => 'device_inactive']);
    });

    it('verifies active device and returns capabilities', function () {
        $this->getJson('/api/v1/kiosk/device/verify', [
            'X-Device-Key' => $this->device->api_key,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'has_tap_to_pay',
                'can_do_door_workflow',
                'can_push_payments',
                'can_accept_card_payments',
                'payment_device',
            ])
            ->assertJson([
                'name' => 'Test Kiosk',
                'has_tap_to_pay' => false,
                'can_do_door_workflow' => false,
            ]);
    });

    it('updates last_seen_at on request', function () {
        expect($this->device->last_seen_at)->toBeNull();

        $this->getJson('/api/v1/kiosk/device/verify', [
            'X-Device-Key' => $this->device->api_key,
        ]);

        $this->device->refresh();
        expect($this->device->last_seen_at)->not->toBeNull();
    });
});

describe('Kiosk: Authentication', function () {
    it('rejects login for non-staff users', function () {
        $regularUser = User::factory()->create();

        $this->postJson('/api/v1/kiosk/login', [
            'email' => $regularUser->email,
            'password' => 'password',
        ], [
            'X-Device-Key' => $this->device->api_key,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('logs in staff user and returns token', function () {
        $this->postJson('/api/v1/kiosk/login', [
            'email' => $this->staffUser->email,
            'password' => 'password',
        ], [
            'X-Device-Key' => $this->device->api_key,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'roles'],
            ]);
    });

    it('returns user info for authenticated request', function () {
        $token = $this->staffUser->createToken('test')->plainTextToken;

        $this->getJson('/api/v1/kiosk/user', [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJson([
                'id' => $this->staffUser->id,
                'name' => $this->staffUser->name,
            ]);
    });

    it('logs out and revokes token', function () {
        $token = $this->staffUser->createToken('test')->plainTextToken;

        $this->postJson('/api/v1/kiosk/logout', [], [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$token}",
        ])
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        // Verify the token was deleted
        expect($this->staffUser->tokens()->count())->toBe(0);
    });
});

describe('Kiosk: Events', function () {
    beforeEach(function () {
        $this->token = $this->staffUser->createToken('test')->plainTextToken;
        $this->headers = [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$this->token}",
        ];
    });

    it('lists events with ticketing enabled', function () {
        $this->getJson('/api/v1/kiosk/events', $this->headers)
            ->assertOk()
            ->assertJsonStructure([
                'events' => [
                    '*' => ['id', 'title', 'start_datetime', 'venue_name', 'ticket_quantity', 'tickets_sold', 'base_price'],
                ],
            ]);
    });

    it('shows single event details', function () {
        $this->getJson("/api/v1/kiosk/events/{$this->event->id}", $this->headers)
            ->assertOk()
            ->assertJson([
                'event' => [
                    'id' => $this->event->id,
                    'title' => 'Test Concert',
                ],
            ]);
    });

    it('returns event stats', function () {
        $this->getJson("/api/v1/kiosk/events/{$this->event->id}/stats", $this->headers)
            ->assertOk()
            ->assertJsonStructure(['sold', 'capacity', 'checked_in']);
    });

    it('returns event pricing', function () {
        $this->getJson("/api/v1/kiosk/events/{$this->event->id}/pricing", $this->headers)
            ->assertOk()
            ->assertJsonStructure(['base_price', 'member_discount', 'max_quantity', 'event_id']);
    });
});

describe('Kiosk: Check-in', function () {
    beforeEach(function () {
        $this->token = $this->staffUser->createToken('test')->plainTextToken;
        $this->headers = [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$this->token}",
        ];

        // Create a door sale with ticket
        $this->order = CreateDoorSale::run(
            event: $this->event,
            quantity: 1,
            paymentMethod: 'cash',
            staffUser: $this->staffUser,
        );
        $this->ticket = $this->order->tickets->first();
    });

    it('checks in a valid ticket', function () {
        $this->postJson("/api/v1/kiosk/events/{$this->event->id}/check-in", [
            'code' => $this->ticket->code,
        ], $this->headers)
            ->assertOk()
            ->assertJson([
                'success' => true,
                'type' => 'success',
            ]);

        $this->ticket->refresh();
        expect($this->ticket->status->value)->toBe('checked_in');
    });

    it('rejects invalid ticket code', function () {
        $this->postJson("/api/v1/kiosk/events/{$this->event->id}/check-in", [
            'code' => 'INVALID123',
        ], $this->headers)
            ->assertOk()
            ->assertJson([
                'success' => false,
                'type' => 'error',
            ]);
    });

    it('warns on already checked in ticket', function () {
        $this->ticket->checkIn($this->staffUser);

        $this->postJson("/api/v1/kiosk/events/{$this->event->id}/check-in", [
            'code' => $this->ticket->code,
        ], $this->headers)
            ->assertOk()
            ->assertJson([
                'success' => false,
                'type' => 'warning',
            ]);
    });

    it('returns recent check-ins', function () {
        $this->ticket->checkIn($this->staffUser);

        $this->getJson("/api/v1/kiosk/events/{$this->event->id}/recent-check-ins", $this->headers)
            ->assertOk()
            ->assertJsonStructure([
                'check_ins' => [
                    '*' => ['id', 'code', 'name', 'checked_in_at'],
                ],
            ]);
    });
});

describe('Kiosk: Door Sales', function () {
    beforeEach(function () {
        $this->token = $this->staffUser->createToken('test')->plainTextToken;
        $this->headers = [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$this->token}",
        ];
    });

    it('creates a cash door sale', function () {
        $this->postJson("/api/v1/kiosk/events/{$this->event->id}/door-sale", [
            'quantity' => 2,
            'payment_method' => 'cash',
            'is_sustaining_member' => false,
        ], $this->headers)
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'order' => ['id', 'uuid', 'quantity', 'total', 'payment_method'],
                'tickets' => [
                    '*' => ['id', 'code'],
                ],
            ]);

        $this->event->refresh();
        expect($this->event->tickets_sold)->toBe(2);
    });

    it('applies member discount to door sale', function () {
        $basePrice = $this->event->getBaseTicketPrice()->getMinorAmount()->toInt();

        $response = $this->postJson("/api/v1/kiosk/events/{$this->event->id}/door-sale", [
            'quantity' => 1,
            'payment_method' => 'cash',
            'is_sustaining_member' => true,
        ], $this->headers)
            ->assertOk();

        $total = $response->json('order.total');
        $expectedTotal = (int) round($basePrice * 0.5); // 50% discount

        expect($total)->toBe($expectedTotal);
    });

    it('returns recent sales', function () {
        CreateDoorSale::run(
            event: $this->event,
            quantity: 1,
            paymentMethod: 'cash',
            staffUser: $this->staffUser,
        );

        $this->getJson("/api/v1/kiosk/events/{$this->event->id}/recent-sales", $this->headers)
            ->assertOk()
            ->assertJsonStructure([
                'sales' => [
                    '*' => ['id', 'uuid', 'quantity', 'total', 'payment_method', 'name', 'completed_at'],
                ],
            ]);
    });
});

describe('Kiosk: User Lookup', function () {
    beforeEach(function () {
        $this->token = $this->staffUser->createToken('test')->plainTextToken;
        $this->headers = [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$this->token}",
        ];
    });

    it('returns not found for unknown email', function () {
        $this->getJson('/api/v1/kiosk/users/lookup?email=unknown@example.com', $this->headers)
            ->assertOk()
            ->assertJson([
                'found' => false,
                'is_sustaining_member' => false,
            ]);
    });

    it('returns user info for known email', function () {
        $user = User::factory()->create(['email' => 'member@test.com']);

        $this->getJson('/api/v1/kiosk/users/lookup?email=member@test.com', $this->headers)
            ->assertOk()
            ->assertJson([
                'found' => true,
                'name' => $user->name,
                'is_sustaining_member' => false,
            ]);
    });

    it('identifies sustaining members', function () {
        $user = User::factory()->create(['email' => 'sustaining@test.com']);
        $user->assignRole('sustaining member');

        $this->getJson('/api/v1/kiosk/users/lookup?email=sustaining@test.com', $this->headers)
            ->assertOk()
            ->assertJson([
                'found' => true,
                'is_sustaining_member' => true,
            ]);
    });
});

describe('Kiosk: Payment Requests', function () {
    beforeEach(function () {
        $this->token = $this->staffUser->createToken('test')->plainTextToken;

        // Create a tap-to-pay device
        $this->tapDevice = KioskDevice::create([
            'name' => 'Tap Device',
            'is_active' => true,
            'has_tap_to_pay' => true,
        ]);

        // Link the regular device to the tap device
        $this->device->update(['payment_device_id' => $this->tapDevice->id]);

        $this->headers = [
            'X-Device-Key' => $this->device->api_key,
            'Authorization' => "Bearer {$this->token}",
        ];
    });

    it('creates a payment request', function () {
        $this->postJson('/api/v1/kiosk/payment-requests', [
            'event_id' => $this->event->id,
            'amount' => 1500,
            'quantity' => 1,
            'customer_email' => 'customer@test.com',
        ], $this->headers)
            ->assertStatus(201)
            ->assertJsonStructure([
                'payment_request' => [
                    'id', 'status', 'amount', 'quantity', 'customer_email', 'event', 'expires_at',
                ],
            ])
            ->assertJson([
                'payment_request' => [
                    'status' => 'pending',
                    'amount' => 1500,
                ],
            ]);
    });

    it('tap device sees pending payment requests', function () {
        // Create a payment request
        $paymentRequest = KioskPaymentRequest::create([
            'source_device_id' => $this->device->id,
            'target_device_id' => $this->tapDevice->id,
            'event_id' => $this->event->id,
            'amount' => 1500,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(2),
        ]);

        $tapToken = $this->staffUser->createToken('tap-test')->plainTextToken;

        $this->getJson('/api/v1/kiosk/payment-requests/pending', [
            'X-Device-Key' => $this->tapDevice->api_key,
            'Authorization' => "Bearer {$tapToken}",
        ])
            ->assertOk()
            ->assertJsonCount(1, 'payment_requests');
    });

    it('can cancel a payment request', function () {
        $paymentRequest = KioskPaymentRequest::create([
            'source_device_id' => $this->device->id,
            'target_device_id' => $this->tapDevice->id,
            'event_id' => $this->event->id,
            'amount' => 1500,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(2),
        ]);

        $this->postJson("/api/v1/kiosk/payment-requests/{$paymentRequest->id}/cancel", [], $this->headers)
            ->assertOk()
            ->assertJson([
                'payment_request' => [
                    'status' => 'cancelled',
                ],
            ]);
    });

    it('tap device can start collection', function () {
        $paymentRequest = KioskPaymentRequest::create([
            'source_device_id' => $this->device->id,
            'target_device_id' => $this->tapDevice->id,
            'event_id' => $this->event->id,
            'amount' => 1500,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(2),
        ]);

        $tapToken = $this->staffUser->createToken('tap-test')->plainTextToken;

        $this->postJson("/api/v1/kiosk/payment-requests/{$paymentRequest->id}/collect", [], [
            'X-Device-Key' => $this->tapDevice->api_key,
            'Authorization' => "Bearer {$tapToken}",
        ])
            ->assertOk()
            ->assertJson([
                'payment_request' => [
                    'status' => 'collecting',
                ],
            ]);
    });

    it('tap device can complete payment', function () {
        $paymentRequest = KioskPaymentRequest::create([
            'source_device_id' => $this->device->id,
            'target_device_id' => $this->tapDevice->id,
            'event_id' => $this->event->id,
            'amount' => 1500,
            'quantity' => 1,
            'status' => 'collecting',
            'expires_at' => now()->addMinutes(2),
        ]);

        $tapToken = $this->staffUser->createToken('tap-test')->plainTextToken;

        $this->postJson("/api/v1/kiosk/payment-requests/{$paymentRequest->id}/complete", [
            'payment_intent_id' => 'pi_test123',
        ], [
            'X-Device-Key' => $this->tapDevice->api_key,
            'Authorization' => "Bearer {$tapToken}",
        ])
            ->assertOk()
            ->assertJson([
                'payment_request' => [
                    'status' => 'completed',
                    'payment_intent_id' => 'pi_test123',
                ],
            ]);
    });
});

describe('Kiosk: Device Capabilities', function () {
    it('identifies tap-to-pay devices correctly', function () {
        $tapDevice = KioskDevice::create([
            'name' => 'Tap Device',
            'is_active' => true,
            'has_tap_to_pay' => true,
        ]);

        expect($tapDevice->canDoDoorWorkflow())->toBeTrue();
        expect($tapDevice->canAcceptCardPayments())->toBeTrue();
        expect($tapDevice->canPushPayments())->toBeFalse();
    });

    it('identifies devices with linked payment device', function () {
        $tapDevice = KioskDevice::create([
            'name' => 'Tap Device',
            'is_active' => true,
            'has_tap_to_pay' => true,
        ]);

        $desktopDevice = KioskDevice::create([
            'name' => 'Desktop',
            'is_active' => true,
            'has_tap_to_pay' => false,
            'payment_device_id' => $tapDevice->id,
        ]);

        expect($desktopDevice->canDoDoorWorkflow())->toBeFalse();
        expect($desktopDevice->canPushPayments())->toBeTrue();
        expect($desktopDevice->canAcceptCardPayments())->toBeTrue();
    });
});

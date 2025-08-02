<?php

namespace Tests\Unit\Models;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservation = Reservation::factory()->create([
            'reserved_at' => Carbon::parse('2025-01-15 14:00:00'),
            'reserved_until' => Carbon::parse('2025-01-15 16:30:00'),
            'cost' => 37.50,
            'hours_used' => 2.5,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
    public function it_calculates_duration_correctly()
    {
        $this->assertEquals(2.5, $this->reservation->duration);
    }

    #[Test]
    public function it_returns_zero_duration_for_incomplete_times()
    {
        $reservation = new Reservation([
            'reserved_at' => Carbon::now(),
            'reserved_until' => null,
        ]);

        $this->assertEquals(0, $reservation->duration);

        $reservation = new Reservation([
            'reserved_at' => null,
            'reserved_until' => Carbon::now(),
        ]);

        $this->assertEquals(0, $reservation->duration);
    }

    #[Test]
    public function it_formats_time_range_for_same_day()
    {
        $expected = 'Jan 15, 2025 2:00 PM - 4:30 PM';
        $this->assertEquals($expected, $this->reservation->time_range);
    }

    #[Test]
    public function it_formats_time_range_for_different_days()
    {
        $this->reservation->reserved_until = Carbon::parse('2025-01-16 10:00:00');

        $expected = 'Jan 15, 2025 2:00 PM - Jan 16, 2025 10:00 AM';
        $this->assertEquals($expected, $this->reservation->time_range);
    }

    #[Test]
    public function it_returns_tbd_for_incomplete_time_range()
    {
        $reservation = new Reservation([
            'reserved_at' => null,
            'reserved_until' => null,
        ]);

        $this->assertEquals('TBD', $reservation->time_range);
    }

    #[Test]
    public function it_checks_if_confirmed()
    {
        $this->assertTrue($this->reservation->isConfirmed());

        $this->reservation->status = 'pending';
        $this->assertFalse($this->reservation->isConfirmed());
    }

    #[Test]
    public function it_checks_if_pending()
    {
        $this->assertFalse($this->reservation->isPending());

        $this->reservation->status = 'pending';
        $this->assertTrue($this->reservation->isPending());
    }

    #[Test]
    public function it_checks_if_cancelled()
    {
        $this->assertFalse($this->reservation->isCancelled());

        $this->reservation->status = 'cancelled';
        $this->assertTrue($this->reservation->isCancelled());
    }

    #[Test]
    public function it_checks_if_upcoming()
    {
        // Set reservation in the future
        $this->reservation->reserved_at = Carbon::now()->addHour();
        $this->assertTrue($this->reservation->isUpcoming());

        // Set reservation in the past
        $this->reservation->reserved_at = Carbon::now()->subHour();
        $this->assertFalse($this->reservation->isUpcoming());
    }

    #[Test]
    public function it_checks_if_in_progress()
    {
        // Set reservation currently in progress
        $this->reservation->reserved_at = Carbon::now()->subHour();
        $this->reservation->reserved_until = Carbon::now()->addHour();
        $this->assertTrue($this->reservation->isInProgress());

        // Set reservation in the future
        $this->reservation->reserved_at = Carbon::now()->addHour();
        $this->reservation->reserved_until = Carbon::now()->addHours(2);
        $this->assertFalse($this->reservation->isInProgress());

        // Set reservation in the past
        $this->reservation->reserved_at = Carbon::now()->subHours(2);
        $this->reservation->reserved_until = Carbon::now()->subHour();
        $this->assertFalse($this->reservation->isInProgress());
    }

    #[Test]
    public function it_formats_cost_display_for_paid_reservation()
    {
        $this->assertEquals('$37.50', $this->reservation->cost_display);
    }

    #[Test]
    public function it_formats_cost_display_for_free_reservation()
    {
        $this->reservation->cost = 0;
        $this->assertEquals('Free', $this->reservation->cost_display);
    }

    #[Test]
    public function it_belongs_to_user()
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $reservation->user);
        $this->assertEquals($user->id, $reservation->user->id);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $reservation = Reservation::factory()->create([
            'reserved_at' => '2025-01-15 14:00:00',
            'reserved_until' => '2025-01-15 16:30:00',
            'cost' => '37.50',
            'hours_used' => '2.5',
            'free_hours_used' => '1.0',
            'is_recurring' => true,
            'recurrence_pattern' => ['weeks' => 4, 'interval' => 1],
        ]);

        $this->assertInstanceOf(Carbon::class, $reservation->reserved_at);
        $this->assertInstanceOf(Carbon::class, $reservation->reserved_until);
        $this->assertIsNumeric($reservation->cost);
        $this->assertIsNumeric($reservation->hours_used);
        $this->assertIsNumeric($reservation->free_hours_used);
        $this->assertIsBool($reservation->is_recurring);
        $this->assertIsArray($reservation->recurrence_pattern);
        $this->assertEquals(4, $reservation->recurrence_pattern['weeks']);
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'status',
            'reserved_at',
            'reserved_until',
            'cost',
            'hours_used',
            'free_hours_used',
            'is_recurring',
            'recurrence_pattern',
            'notes',
        ];

        $reservation = new Reservation;
        $this->assertEquals($fillable, $reservation->getFillable());
    }

    #[Test]
    public function it_creates_reservation_with_factory()
    {
        $reservation = Reservation::factory()->confirmed()->create();

        $this->assertEquals('confirmed', $reservation->status);
        $this->assertInstanceOf(User::class, $reservation->user);
        $this->assertInstanceOf(Carbon::class, $reservation->reserved_at);
        $this->assertInstanceOf(Carbon::class, $reservation->reserved_until);
        $this->assertGreaterThan(0, $reservation->duration);
    }

    #[Test]
    public function it_creates_free_reservation_with_factory()
    {
        $reservation = Reservation::factory()->free()->create();

        $this->assertEquals(0, $reservation->cost);
        $this->assertEquals('Free', $reservation->cost_display);
    }

    #[Test]
    public function it_creates_recurring_reservation_with_factory()
    {
        $reservation = Reservation::factory()->recurring()->create();

        $this->assertTrue($reservation->is_recurring);
        $this->assertNotNull($reservation->recurrence_pattern);
        $this->assertArrayHasKey('weeks', $reservation->recurrence_pattern);
        $this->assertArrayHasKey('interval', $reservation->recurrence_pattern);
    }

    #[Test]
    public function it_creates_upcoming_reservation_with_factory()
    {
        $reservation = Reservation::factory()->upcoming()->create();

        $this->assertTrue($reservation->isUpcoming());
        $this->assertEquals('confirmed', $reservation->status);
    }

    #[Test]
    public function it_creates_past_reservation_with_factory()
    {
        $reservation = Reservation::factory()->past()->create();

        $this->assertFalse($reservation->isUpcoming());
        $this->assertTrue($reservation->reserved_at->isPast());
        $this->assertEquals('confirmed', $reservation->status);
    }
}

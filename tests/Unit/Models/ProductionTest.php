<?php

namespace Tests\Unit\Models;

use App\Data\LocationData;
use App\Models\BandProfile;
use App\Models\Production;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    protected Production $production;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->manager = User::factory()->create();
        $this->production = Production::factory()->create([
            'manager_id' => $this->manager->id,
        ]);
    }

    /** @test */
    public function it_belongs_to_a_manager()
    {
        $this->assertInstanceOf(User::class, $this->production->manager);
        $this->assertEquals($this->manager->id, $this->production->manager->id);
    }

    /** @test */
    public function it_can_have_performers()
    {
        $band1 = BandProfile::factory()->create();
        $band2 = BandProfile::factory()->create();

        $this->production->performers()->attach($band1->id, ['order' => 1, 'set_length' => 30]);
        $this->production->performers()->attach($band2->id, ['order' => 2, 'set_length' => 45]);

        $performers = $this->production->performers;
        
        $this->assertCount(2, $performers);
        $this->assertEquals(1, $performers->first()->pivot->order);
        $this->assertEquals(30, $performers->first()->pivot->set_length);
    }

    /** @test */
    public function it_can_have_a_reservation()
    {
        $reservation = Reservation::factory()->create([
            'production_id' => $this->production->id,
        ]);

        $this->assertInstanceOf(Reservation::class, $this->production->reservation);
        $this->assertEquals($reservation->id, $this->production->reservation->id);
    }

    /** @test */
    public function it_can_have_genres_as_tags()
    {
        $this->production->attachTag('rock', 'genre');
        $this->production->attachTag('indie', 'genre');

        $genres = $this->production->genres;
        
        $this->assertCount(2, $genres);
        $this->assertTrue($genres->pluck('name')->contains('rock'));
        $this->assertTrue($genres->pluck('name')->contains('indie'));
    }

    /** @test */
    public function it_formats_date_range_for_same_day()
    {
        $start = Carbon::parse('2025-03-15 19:00:00');
        $end = Carbon::parse('2025-03-15 22:00:00');
        
        $this->production->update([
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $expected = 'Mar 15, 2025 7:00 PM - 10:00 PM';
        $this->assertEquals($expected, $this->production->date_range);
    }

    /** @test */
    public function it_formats_date_range_for_different_days()
    {
        $start = Carbon::parse('2025-03-15 19:00:00');
        $end = Carbon::parse('2025-03-16 01:00:00');
        
        $this->production->update([
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $expected = 'Mar 15, 2025 7:00 PM - Mar 16, 2025 1:00 AM';
        $this->assertEquals($expected, $this->production->date_range);
    }

    /** @test */
    public function it_returns_start_time_only_when_end_time_missing()
    {
        $start = Carbon::parse('2025-03-15 19:00:00');
        $this->production->update([
            'start_time' => $start,
            'end_time' => null,
        ]);

        $expected = 'Mar 15, 2025 7:00 PM';
        $this->assertEquals($expected, $this->production->date_range);
    }

    /** @test */
    public function it_checks_if_user_is_manager()
    {
        $otherUser = User::factory()->create();

        $this->assertTrue($this->production->isManageredBy($this->manager));
        $this->assertFalse($this->production->isManageredBy($otherUser));
    }

    /** @test */
    public function it_checks_if_published()
    {
        // Not published yet
        $this->production->update(['published_at' => null]);
        $this->assertFalse($this->production->isPublished());

        // Published in future
        $this->production->update(['published_at' => Carbon::now()->addDay()]);
        $this->assertFalse($this->production->isPublished());

        // Already published
        $this->production->update(['published_at' => Carbon::now()->subDay()]);
        $this->assertTrue($this->production->isPublished());
    }

    /** @test */
    public function it_checks_if_upcoming()
    {
        // Past event
        $this->production->update(['start_time' => Carbon::now()->subDay()]);
        $this->assertFalse($this->production->isUpcoming());

        // Future event
        $this->production->update(['start_time' => Carbon::now()->addDay()]);
        $this->assertTrue($this->production->isUpcoming());
    }

    /** @test */
    public function it_calculates_estimated_duration()
    {
        $band1 = BandProfile::factory()->create();
        $band2 = BandProfile::factory()->create();

        $this->production->performers()->attach($band1->id, ['set_length' => 30]);
        $this->production->performers()->attach($band2->id, ['set_length' => 45]);

        $this->assertEquals(75, $this->production->estimated_duration);
    }

    /** @test */
    public function it_returns_zero_duration_with_no_performers()
    {
        $this->assertEquals(0, $this->production->estimated_duration);
    }

    /** @test */
    public function it_checks_for_external_venue()
    {
        // Default CMC location
        $cmcLocation = LocationData::cmc();
        $this->production->update(['location' => $cmcLocation]);
        $this->assertFalse($this->production->isExternalVenue());

        // External venue
        $externalLocation = LocationData::external('The Crystal Ballroom');
        $this->production->update(['location' => $externalLocation]);
        $this->assertTrue($this->production->isExternalVenue());
    }

    /** @test */
    public function it_gets_venue_name()
    {
        // Create production with CMC location specifically
        $production = Production::factory()->create([
            'location' => LocationData::cmc(),
            'manager_id' => $this->manager->id,
        ]);
        $this->assertEquals('Corvallis Music Collective', $production->venue_name);

        // External venue
        $externalLocation = LocationData::external('The Crystal Ballroom');
        $production->update(['location' => $externalLocation]);
        $this->assertEquals('External Venue', $production->venue_name);
    }

    /** @test */
    public function it_checks_if_has_tickets()
    {
        $this->production->update(['ticket_url' => null]);
        $this->assertFalse($this->production->hasTickets());

        $this->production->update(['ticket_url' => '']);
        $this->assertFalse($this->production->hasTickets());

        $this->production->update(['ticket_url' => 'https://example.com/tickets']);
        $this->assertTrue($this->production->hasTickets());
    }

    /** @test */
    public function it_normalizes_ticket_url()
    {
        $this->production->update(['ticket_url' => 'example.com/tickets']);
        $this->assertEquals('https://example.com/tickets', $this->production->ticket_url);

        $this->production->update(['ticket_url' => 'https://example.com/tickets']);
        $this->assertEquals('https://example.com/tickets', $this->production->ticket_url);
    }

    /** @test */
    public function it_handles_notaflof_flag()
    {
        $this->assertFalse($this->production->isNotaflof());

        $this->production->setNotaflof(true);
        $this->assertTrue($this->production->isNotaflof());

        $this->production->setNotaflof(false);
        $this->assertFalse($this->production->isNotaflof());
    }

    /** @test */
    public function it_formats_ticket_price_display()
    {
        // Free event (no ticket URL)
        $this->production->update(['ticket_url' => null]);
        $this->assertEquals('Free', $this->production->ticket_price_display);

        // Ticketed event with price
        $this->production->update([
            'ticket_url' => 'https://example.com',
            'ticket_price' => 15.50
        ]);
        $this->assertEquals('$15.50', $this->production->ticket_price_display);

        // Ticketed event without price
        $this->production->update([
            'ticket_url' => 'https://example.com',
            'ticket_price' => null
        ]);
        $this->assertEquals('Ticketed', $this->production->ticket_price_display);

        // NOTAFLOF event
        $this->production->update([
            'ticket_url' => 'https://example.com',
            'ticket_price' => 20.00
        ]);
        $this->production->setNotaflof(true);
        $this->assertEquals('$20.00 (NOTAFLOF)', $this->production->ticket_price_display);
    }

    /** @test */
    public function it_checks_if_free()
    {
        // No tickets
        $this->production->update(['ticket_url' => null]);
        $this->assertTrue($this->production->isFree());

        // Tickets but no price
        $this->production->update([
            'ticket_url' => 'https://example.com',
            'ticket_price' => null
        ]);
        $this->assertTrue($this->production->isFree());

        // Tickets with zero price
        $this->production->update([
            'ticket_url' => 'https://example.com',
            'ticket_price' => 0
        ]);
        $this->assertTrue($this->production->isFree());

        // Tickets with price
        $this->production->update([
            'ticket_url' => 'https://example.com',
            'ticket_price' => 15.00
        ]);
        $this->assertFalse($this->production->isFree());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $start = Carbon::parse('2025-03-15 19:00:00');
        $location = LocationData::cmc();
        
        $this->production->update([
            'start_time' => $start,
            'location' => $location
        ]);

        $this->assertInstanceOf(Carbon::class, $this->production->start_time);
        $this->assertInstanceOf(LocationData::class, $this->production->location);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'title', 'description', 'start_time', 'end_time', 'doors_time',
            'location', 'ticket_url', 'ticket_price', 'status', 'published_at', 'manager_id'
        ];

        $this->assertEquals($fillable, $this->production->getFillable());
    }

    /** @test */
    public function it_sets_default_location_on_creation()
    {
        $production = Production::factory()->create(['location' => null]);
        
        $this->assertInstanceOf(LocationData::class, $production->location);
        $this->assertEquals('Corvallis Music Collective', $production->location->getVenueName());
    }
}
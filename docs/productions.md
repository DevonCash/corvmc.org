# Production System

The Production System manages events, shows, and performances for the Corvallis Music Collective, providing comprehensive tools for event planning, band booking, ticketing, and venue management.

## Business Logic & Workflow

The production system serves as the central hub for all events and shows organized by or hosted at the Corvallis Music Collective. Productions represent any type of musical event, from intimate acoustic sets to full concerts with multiple bands. Each production is managed by a designated user who handles booking, logistics, and event details. The system supports both internal events at the collective's space and external events at other venues, providing flexibility for the organization's diverse programming needs.

The workflow begins when an authorized user creates a production, filling in essential details like title, description, date/time, and venue information. They can then attach bands as performers, specifying performance order and set lengths to help with scheduling. The system handles ticketing information, including pricing, external ticket URLs, and special pricing models like NOTAFLOF (No One Turned Away For Lack of Funds). Productions can be linked to practice space reservations when bands need rehearsal time before their performance.

The system provides robust event management features including publication scheduling, genre tagging for discoverability, and media management for promotional materials like posters. Productions integrate with the collective's broader ecosystem - bands from the band profile system can be booked as performers, and the reservation system can coordinate practice space needs. This creates a seamless experience from initial booking through event execution, while maintaining detailed records for reporting and community engagement.

## API Reference

### Production Model

#### Properties
```php
// Event Details
public string $title;                  // Event name/title
public string $description;            // HTML-formatted event description
public ?datetime $start_time;          // Event start time
public ?datetime $end_time;            // Event end time  
public ?datetime $doors_time;          // Door opening time
public ?datetime $published_at;        // Publication timestamp
public string $status;                 // Event status

// Venue & Location
public LocationData $location;         // Venue information (internal/external)

// Ticketing
public ?string $ticket_url;            // External ticketing URL
public ?float $ticket_price;           // Ticket price in dollars

// Management
public int $manager_id;                // User ID of event manager

// Relationships  
public User $manager;                  // Event manager
public Collection $performers;         // Bands performing (via pivot)
public ?Reservation $reservation;      // Associated practice space reservation
public Collection $tags;               // Event genres (via Spatie Tags)
public Collection $media;              // Promotional materials (via Spatie MediaLibrary)
public Collection $flags;              // Event flags like NOTAFLOF (via Spatie ModelFlags)
```

#### Key Methods
```php
// Management & Access
public function isManageredBy(User $user): bool
// Check if user manages this production

// Date & Time Formatting
public function getDateRangeAttribute(): string
// Get formatted date/time range for display

// Publication & Status
public function isPublished(): bool
// Check if production is published and live

public function isUpcoming(): bool  
// Check if production is in the future

// Performer Management
public function getEstimatedDurationAttribute(): int
// Calculate total estimated duration from performer set lengths

// Venue Information
public function isExternalVenue(): bool
// Check if event is at external venue

public function getVenueNameAttribute(): string
// Get venue name (defaults to "Corvallis Music Collective")

public function getVenueDetailsAttribute(): string
// Get full venue details for display

// Ticketing
public function hasTickets(): bool
// Check if event has ticketing information

public function getTicketUrlAttribute($value): ?string
// Get ticket URL with protocol validation

public function getTicketPriceDisplayAttribute(): string
// Get formatted ticket price with NOTAFLOF notation

public function isFree(): bool
// Check if event is free

// NOTAFLOF Support
public function isNotaflof(): bool
// Check if event uses NOTAFLOF pricing

public function setNotaflof(bool $notaflof = true): self
// Set or unset NOTAFLOF flag

// Media Management
public function getPosterUrlAttribute()
// Get event poster URL

public function getPosterThumbUrlAttribute()
// Get poster thumbnail URL

public function registerMediaConversions(?Media $media = null): void
// Configure image conversions (300x200 poster thumbs)

// Tag Management
public function getGenresAttribute()
// Get event genre tags
```

### Performers Pivot Table (production_bands)
```php
// Pivot table fields
public int $production_id;             // Production ID
public int $band_profile_id;           // Band ID
public int $order;                     // Performance order (1st, 2nd, etc.)
public ?int $set_length;               // Set length in minutes
public timestamp $created_at;          // Booking timestamp
public timestamp $updated_at;          // Last updated
```

### LocationData Value Object
```php
// Location information
public bool $is_external;              // Whether venue is external
public ?string $venue_name;            // External venue name
public ?string $address;               // Venue address
public ?string $city;                  // Venue city
public ?string $state;                 // Venue state
public ?string $details;               // Additional venue details

// Methods
public function isExternal(): bool
// Check if location is external venue

public function getVenueName(): string
// Get venue name with fallback

public function getVenueDetails(): string  
// Get formatted venue details

public static function cmc(): self
// Create default CMC location
```

## Usage Examples

### Creating and Managing Productions
```php
// Create a new production
$manager = User::find(1);
$production = Production::create([
    'title' => 'Friday Night Live',
    'description' => '<p>Weekly showcase featuring local bands</p>',
    'start_time' => now()->addWeek()->setTime(20, 0),
    'end_time' => now()->addWeek()->setTime(23, 0),
    'doors_time' => now()->addWeek()->setTime(19, 30),
    'manager_id' => $manager->id,
    'status' => 'planned'
]);

// Set up ticketing
$production->update([
    'ticket_url' => 'https://tickets.example.com/friday-live',
    'ticket_price' => 10.00
]);

// Enable NOTAFLOF
$production->setNotaflof(true);
```

### Managing Performers  
```php
// Add bands as performers
$band1 = BandProfile::find(1);
$band2 = BandProfile::find(2);

$production->performers()->attach($band1, [
    'order' => 1,
    'set_length' => 30
]);

$production->performers()->attach($band2, [
    'order' => 2, 
    'set_length' => 45
]);

// Get estimated total duration
$totalDuration = $production->estimated_duration; // 75 minutes
```

### Event Publishing and Media
```php
// Publish the event
$production->update(['published_at' => now()]);

// Add poster
$production->addMediaFromRequest('poster')
          ->toMediaCollection('poster');

// Add genre tags
$production->attachTag('Rock', 'genre');
$production->attachTag('Local', 'genre');
```

### Venue Management
```php
// External venue
$production->update([
    'location' => new LocationData(
        is_external: true,
        venue_name: 'The Beanery',
        address: '500 SW 3rd St',
        city: 'Corvallis',
        state: 'OR'
    )
]);

// Check venue type
if ($production->isExternalVenue()) {
    echo "Event at: " . $production->venue_name;
} else {
    echo "Event at Corvallis Music Collective";
}
```

### Event Information Display
```php
// Get formatted event details
$dateRange = $production->date_range;
$ticketInfo = $production->ticket_price_display;
$venue = $production->venue_details;

// Check event status
if ($production->isPublished() && $production->isUpcoming()) {
    echo "Upcoming event: " . $production->title;
    echo "When: " . $dateRange;
    echo "Where: " . $venue;
    echo "Tickets: " . $ticketInfo;
}
```

### Linking with Reservations
```php
// Associate with practice space reservation
$reservation = Reservation::create([
    'user_id' => $manager->id,
    'production_id' => $production->id,
    'reserved_at' => $production->start_time->subHours(2),
    'reserved_until' => $production->start_time->subHour(1),
    'purpose' => 'sound check'
]);
```

## Integration Points

- **Band Profiles**: Performers linked to band profile system
- **User Management**: Production managers are platform users
- **Reservation System**: Productions can have associated practice space bookings
- **Media Library**: Poster and promotional material management via Spatie MediaLibrary
- **Tagging System**: Genre classification via Spatie Tags
- **Flag System**: NOTAFLOF and other event flags via Spatie ModelFlags
- **Filament Admin**: Event management through admin interface
- **Member Profiles**: Manager roles reference user profiles

## Business Rules

- Each production has exactly one manager who controls all aspects
- Performers are ordered by the 'order' field in the pivot table
- Default location is Corvallis Music Collective unless specified as external
- Ticket URLs are automatically prefixed with https:// if no protocol provided
- NOTAFLOF flag adds "(NOTAFLOF)" to ticket price display
- Productions can be published in advance with scheduled publication dates
- Free events show "Free" regardless of NOTAFLOF status
- Set lengths are used to calculate total estimated event duration
- Events can exist without performers (for non-musical events or TBD lineups)
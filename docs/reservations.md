# Reservation System

The Reservation System manages practice space bookings for the Corvallis Music Collective, providing scheduling, pricing, and availability management for the collective's rehearsal facilities.

## Business Logic & Workflow

The reservation system operates on a hybrid pricing model that supports both paying users and sustaining members with benefits. Regular users pay $15 per hour for practice space, while sustaining members receive 4 free hours per month before reverting to the standard hourly rate. The system enforces business rules including minimum 1-hour bookings, maximum 8-hour sessions, and business hours constraints (8 AM to 10 PM). All reservations must be made for future dates, and the system prevents double-booking by checking for conflicts before confirming reservations.

The workflow begins when a user selects an available time slot and submits a reservation request. The system validates the request against business rules, checks for scheduling conflicts, and calculates the appropriate cost based on the user's membership status and available free hours. Once confirmed, the reservation is tracked with status updates (pending, confirmed, cancelled) and integrates with the production system for event-related practice sessions. Users can modify their reservations subject to the same validation rules, and the system maintains detailed records for billing and usage analytics.

Sustaining members receive enhanced features including the ability to create recurring weekly reservations, which automatically books the same time slot for multiple weeks while skipping any conflicting dates. The system tracks free hour usage on a monthly basis, resetting each month to ensure fair distribution of benefits. Administrative functions provide insights into space utilization, member activity, and revenue generation, supporting the collective's operational and financial planning needs.

## API Reference

### Reservation Model

#### Properties
```php
// Scheduling
public int $user_id;                   // User who made the reservation
public datetime $reserved_at;          // Start time of reservation
public datetime $reserved_until;       // End time of reservation
public string $status;                 // 'pending', 'confirmed', 'cancelled'

// Billing & Costs
public float $total_cost;              // Total cost in dollars
public float $free_hours_used;         // Free hours applied (sustaining members)
public float $paid_hours;              // Hours charged at $15/hour

// Event Integration
public ?int $production_id;            // Optional linked production/event
public ?string $purpose;               // Reservation purpose/notes

// Relationships
public User $user;                     // User who made reservation  
public ?Production $production;        // Associated production (optional)
```

#### Key Methods
```php
// Duration Calculations
public function getDurationAttribute(): float
// Get reservation duration in hours

public function getDurationInMinutesAttribute(): int
// Get reservation duration in minutes

// Time & Status Checks
public function getTimeRangeAttribute(): string
// Get formatted time range for display

public function isConfirmed(): bool
// Check if reservation is confirmed

public function isPending(): bool  
// Check if reservation is pending

public function isCancelled(): bool
// Check if reservation is cancelled

public function isUpcoming(): bool
// Check if reservation is in the future

public function isInProgress(): bool
// Check if reservation is currently active

// Cost Display
public function getCostDisplayAttribute(): string
// Get formatted cost for display ("$30.00" or "Free")
```

## ReservationService API

### Core Booking Operations
```php
public function createReservation(
    User $user,
    datetime $startTime,
    datetime $endTime,
    array $options = []
): Reservation
// Create a new reservation with validation and cost calculation
// Options: production_id, purpose, skip_validation

public function updateReservation(
    Reservation $reservation,
    datetime $newStartTime,
    datetime $newEndTime,
    array $options = []
): bool
// Update existing reservation with revalidation

public function cancelReservation(Reservation $reservation): bool
// Cancel a reservation (sets status to 'cancelled')
```

### Validation & Business Rules
```php
public function validateReservation(
    datetime $startTime,
    datetime $endTime,
    ?User $user = null,
    ?Reservation $excludeReservation = null
): array
// Validate reservation against all business rules
// Returns: ['valid' => bool, 'errors' => array]

public function isTimeSlotAvailable(
    datetime $startTime,
    datetime $endTime,
    ?Reservation $excludeReservation = null
): bool
// Check if time slot is available (no conflicts)

public function findConflictingReservations(
    datetime $startTime,
    datetime $endTime,
    ?Reservation $excludeReservation = null
): Collection
// Find reservations that conflict with given time range
```

### Cost Calculations
```php
public function calculateCost(
    User $user,
    datetime $startTime,  
    datetime $endTime
): array
// Calculate reservation cost for user
// Returns: [
//   'total_cost' => float,
//   'free_hours_used' => float,
//   'paid_hours' => float,
//   'hourly_rate' => float
// ]

public function calculateHours(datetime $startTime, datetime $endTime): float
// Calculate duration in hours between two times
```

### Recurring Reservations (Sustaining Members Only)
```php
public function createRecurringReservation(
    User $user,
    datetime $startTime,
    datetime $endTime, 
    int $weeks,
    array $options = []
): array
// Create recurring weekly reservation
// Returns: ['created' => array, 'skipped' => array, 'errors' => array]
```

### Availability & Scheduling
```php
public function getAvailableTimeSlots(
    datetime $date,
    int $durationMinutes = 60
): array
// Get available time slots for given date and duration
// Returns array of ['start' => datetime, 'end' => datetime]

public function getUserStats(User $user): array
// Get user's reservation statistics
// Returns: [
//   'total_reservations' => int,
//   'total_hours' => float,
//   'total_cost' => float,
//   'free_hours_used_this_month' => float,
//   'upcoming_reservations' => int
// ]
```

## Usage Examples

### Making Basic Reservations
```php
$reservationService = new ReservationService();
$user = User::find(1);

// Create a 2-hour practice session
$startTime = now()->addDay()->setTime(14, 0); // Tomorrow 2 PM  
$endTime = $startTime->copy()->addHours(2);   // Until 4 PM

$reservation = $reservationService->createReservation(
    $user, 
    $startTime, 
    $endTime,
    ['purpose' => 'Band practice']
);

echo "Reserved for " . $reservation->duration . " hours";
echo "Total cost: " . $reservation->cost_display;
```

### Handling Sustaining Member Benefits
```php
$sustainingMember = User::find(2);
$sustainingMember->assignRole('sustaining member');

// Create reservation - will use free hours if available
$reservation = $reservationService->createReservation(
    $sustainingMember,
    now()->addDay()->setTime(19, 0),
    now()->addDay()->setTime(22, 0) // 3 hours
);

// Check how free hours were applied
echo "Free hours used: " . $reservation->free_hours_used;
echo "Paid hours: " . $reservation->paid_hours;
echo "Total cost: $" . $reservation->total_cost;
```

### Creating Recurring Reservations
```php
// Sustaining members can create recurring reservations
$result = $reservationService->createRecurringReservation(
    $sustainingMember,
    now()->next('Wednesday')->setTime(19, 0), // Next Wednesday 7 PM
    now()->next('Wednesday')->setTime(21, 0), // Until 9 PM  
    8 // For 8 weeks
);

echo "Created: " . count($result['created']) . " reservations";
echo "Skipped: " . count($result['skipped']) . " (conflicts)";
```

### Availability Checking
```php
// Check if time slot is available
$isAvailable = $reservationService->isTimeSlotAvailable(
    now()->addWeek()->setTime(18, 0),
    now()->addWeek()->setTime(20, 0)
);

// Get all available 2-hour slots for tomorrow
$availableSlots = $reservationService->getAvailableTimeSlots(
    now()->addDay(),
    120 // 2 hours in minutes
);

foreach ($availableSlots as $slot) {
    echo $slot['start']->format('g:i A') . " - " . $slot['end']->format('g:i A') . "\n";
}
```

### Validation and Error Handling
```php
// Validate reservation before creating
$validation = $reservationService->validateReservation(
    now()->addDay()->setTime(23, 0), // 11 PM start (after hours)
    now()->addDay()->setTime(24, 0), // Midnight end
    $user
);

if (!$validation['valid']) {
    foreach ($validation['errors'] as $error) {
        echo "Error: " . $error . "\n";
    }
    // Output: "Error: Reservation must be within business hours (8 AM - 10 PM)"
}
```

### Integration with Productions
```php
// Link reservation to a production for sound check
$production = Production::find(1);

$soundCheckReservation = $reservationService->createReservation(
    $production->manager,
    $production->start_time->subHours(2), // 2 hours before show
    $production->start_time->subHour(1),  // 1 hour before show
    [
        'production_id' => $production->id,
        'purpose' => 'Sound check and setup'
    ]
);
```

### User Statistics and Analytics
```php
// Get user's reservation statistics
$stats = $reservationService->getUserStats($user);

echo "Total reservations: " . $stats['total_reservations'];
echo "Total hours booked: " . $stats['total_hours'];
echo "Total spent: $" . $stats['total_cost'];
echo "Free hours used this month: " . $stats['free_hours_used_this_month'];
```

## Integration Points

- **User System**: All reservations tied to user accounts with membership status
- **Subscription System**: Sustaining member benefits and free hour tracking  
- **Production System**: Reservations can be linked to events for setup/rehearsal
- **Billing System**: Cost calculations and payment tracking
- **Calendar System**: Availability checking and conflict resolution
- **Administrative Dashboard**: Usage statistics and facility management
- **Permission System**: Role-based access to recurring reservations

## Business Rules & Constraints

- **Operating Hours**: 8 AM to 10 PM daily
- **Minimum Duration**: 1 hour reservations
- **Maximum Duration**: 8 hours per reservation
- **Advance Booking**: All reservations must be for future dates/times
- **Pricing**: $15/hour standard rate
- **Sustaining Member Benefits**: 4 free hours per month, resets monthly
- **Recurring Reservations**: Available only to sustaining members
- **Conflict Prevention**: No overlapping reservations allowed
- **Cancellation**: Reservations can be cancelled but not deleted
- **Status Tracking**: Pending → Confirmed → (Cancelled) workflow